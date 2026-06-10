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

## Kernel/Foundation observability dependency normalization

- Status: candidate
- Source epic: `1.330.0 Kernel: Artifacts (manifest + config) + fingerprint + cache:verify core`
- Owner area: `core/kernel`, `core/foundation`
- Priority: later cleanup
- Type: observability dependency model / DI consistency

### Goal

Normalize observability dependency injection across Kernel/Foundation runtime services so business services depend only on public observability ports/interfaces and do not know whether the injected implementation is a real adapter or a Noop adapter.

### Current state

The codebase currently has a mixed observability dependency model.

Some services use a stricter non-null port model. For example, `KernelRuntime` receives `LoggerInterface`, `TracerPortInterface`, and `MeterPortInterface` as constructor dependencies and emits lifecycle telemetry through those ports while swallowing observability failures so telemetry cannot change runtime behavior.

Other services still use a transitional nullable model. For example, `ModulePlanResolver` uses a non-null `MeterPortInterface`, but accepts nullable `?LoggerInterface` and branches when the logger is absent. `PriorityResetOrchestrator` also accepts nullable tracer, meter, and logger ports.

This is acceptable as a transitional state, but new artifact/fingerprint/cache services should not copy the nullable model.

### Candidate policy

- Runtime/business services depend only on observability ports/interfaces:
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`;
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`;
  - `Psr\Log\LoggerInterface`;
  - `Coretsia\Foundation\Time\Stopwatch` where duration measurement is needed.
- Services MUST NOT know whether observability dependencies are real adapters or Noop adapters.
- Services MUST NOT instantiate Noop implementations directly.
- Services SHOULD NOT branch on observability availability.
- New services SHOULD prefer non-null observability dependencies.
- Default real-vs-Noop binding is owned by provider/composition/default binding layer, not by business services.
- Observability failures MUST NOT change business behavior, cache verification semantics, artifact write behavior, fingerprint calculation behavior, or runtime lifecycle failure precedence.
- Low-level helpers and pure value/normalization services SHOULD NOT emit observability directly.
- Observability should live at operation-boundary services.

### Candidate files

```text
framework/packages/core/kernel/src/Runtime/KernelRuntime.php
framework/packages/core/kernel/src/Config/ConfigKernel.php
framework/packages/core/kernel/src/Module/ModulePlanResolver.php
framework/packages/core/foundation/src/Runtime/Reset/PriorityResetOrchestrator.php

framework/packages/core/foundation/src/Logging/NoopLogger.php
framework/packages/core/foundation/src/Observability/Metrics/NoopMeter.php
framework/packages/core/foundation/src/Observability/Tracing/NoopTracer.php
framework/packages/core/foundation/src/Observability/Errors/NoopErrorReporter.php
framework/packages/core/foundation/src/Observability/Profiling/NoopProfiler.php
framework/packages/core/foundation/src/Observability/Profiling/NoopProfilingSession.php
framework/packages/core/foundation/src/Observability/Tracing/NoopContextPropagation.php
framework/packages/core/foundation/src/Observability/Tracing/NoopSpan.php

framework/packages/core/kernel/src/Artifacts/ArtifactWriter.php
framework/packages/core/kernel/src/Artifacts/Fingerprint/FingerprintCalculator.php
framework/packages/core/kernel/src/Artifacts/Verifier/CacheVerifier.php
framework/packages/core/kernel/src/Provider/KernelServiceFactory.php
framework/packages/core/kernel/src/Provider/KernelServiceProvider.php
```

### Target model

```text
operation-boundary service
→ LoggerInterface / MeterPortInterface / TracerPortInterface / Stopwatch
→ provider/composition/default binding layer
→ real adapter OR Noop adapter
```

Services should not implement this model:

```text
operation-boundary service
→ if logger missing, skip logging
→ if meter missing, skip metrics
→ if tracer missing, skip spans
→ instantiate Noop* manually
```

### Why not now

`1.330.0` is focused on Kernel-owned artifacts, deterministic fingerprinting, cache verification, and artifact byte stability.

Normalizing existing observability dependency style across Kernel/Foundation would expand the scope into already implemented runtime/config/module/reset services and risks regressions outside the artifact/fingerprint/cache boundary.

For `1.330.0`, the active rule is narrower:

- new artifact/fingerprint/cache services use observability ports/interfaces only;
- new artifact/fingerprint/cache services do not instantiate Noop implementations;
- new artifact/fingerprint/cache services do not know whether dependencies are real or Noop;
- Noop-specific binding policy is not owned by artifact services.

### Promotion condition

Promote only through a numbered epic, ADR, or SSoT update after artifact/fingerprint/cache verification is implemented and stable.

Promotion is appropriate when one of the following happens:

- provider/default observability bindings become part of a formal SSoT policy;
- nullable observability dependencies start creating inconsistent service behavior;
- package compliance wants to enforce non-null observability ports for operation-boundary services;
- observability tests need one consistent dependency model across Kernel/Foundation.

### Possible future epic shape

```text
1.xxx.0 Kernel/Foundation: Observability Dependency Normalization
```

Potential deliverables:

- define the canonical observability dependency model for operation-boundary services;
- decide whether `LoggerInterface`, `MeterPortInterface`, and `TracerPortInterface` should be non-null in Kernel/Foundation runtime services;
- move real-vs-Noop binding responsibility into provider/composition/default binding layer;
- remove nullable observability branches where appropriate;
- ensure services never instantiate Noop implementations directly;
- add contract tests proving services depend on ports/interfaces only;
- add tests proving observability failures do not change business behavior;
- update `docs/ssot/observability.md`;
- update relevant package READMEs.

---
