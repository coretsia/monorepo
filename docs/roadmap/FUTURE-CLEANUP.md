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

## Foundation compiled autowire metadata

- Status: candidate
- Source epic: `1.200.0 Foundation: DI Container + Tags + DeterministicOrder + Reset orchestration`
- Owner area: `core/foundation`
- Priority: later cleanup
- Type: container architecture / autowire metadata / runtime reflection boundary

### Goal

Define a deterministic no-runtime-reflection path for concrete-class autowiring.

The current Foundation container supports conservative reflection-based concrete autowiring through:

```text
foundation.container.autowire_concrete
foundation.container.allow_reflection_for_concrete
```

In the current runtime implementation, concrete autowiring is allowed only when both flags are `true`.

A future cleanup may allow:

```text
foundation.container.autowire_concrete = true
foundation.container.allow_reflection_for_concrete = false
```

to mean:

```text
concrete autowiring is allowed, but constructor metadata must come from a deterministic compiled metadata source instead of runtime reflection.
```

### Candidate policy

A future no-reflection autowire mode would require a canonical source of truth for constructor metadata.

The metadata source MUST NOT be an arbitrary runtime array without schema ownership.

It should have a deterministic shape with:

- stable keys;
- stable ordering;
- schema version;
- no raw reflection dumps;
- no environment-dependent values;
- no filesystem-order dependency;
- no constructor argument value dumps;
- no service instance dumps;
- generated artifact ownership or explicit provider metadata ownership.

A conceptual shape could be:

```php
[
    SomeService::class => [
        'arguments' => [
            DependencyA::class,
            DependencyB::class,
        ],
    ],
]
```

The actual accepted shape would need to be defined by a numbered epic, ADR, or SSoT update before implementation.

### Candidate implementation shape

The current `Container` constructor receives:

```text
definitions
instances
config
definitionShared
```

A future compiled autowire mode would likely require an additional input such as:

```text
autowireMetadata
```

or a dedicated value object such as:

```text
ContainerAutowirePlan
```

This should not be added until the metadata source, schema, ownership, and diagnostics boundaries are defined.

`Container::canAutowire()` would need to distinguish reflection and metadata modes.

Current behavior is effectively:

```php
if (!$autowireConcrete || !$allowReflection) {
    return false;
}

$reflection = new \ReflectionClass($id);

return $reflection->isInstantiable();
```

A future implementation may need a shape closer to:

```php
if (!$autowireConcrete) {
    return false;
}

if ($allowReflection) {
    return $this->canAutowireWithReflection($id);
}

return $this->canAutowireFromCompiledMetadata($id);
```

This only makes sense once a compiled metadata source exists.

`Container::autowire()` would also need to split current reflection-based behavior from metadata-based behavior.

Current behavior is reflection-based:

```php
$reflection = new \ReflectionClass($className);
$constructor = $reflection->getConstructor();
```

A future implementation may need separate paths:

```text
autowireWithReflection()
autowireFromCompiledMetadata()
```

Without this split, `allow_reflection_for_concrete = false` cannot provide a real alternative concrete autowire path.

### Candidate files

```text
framework/packages/core/foundation/src/Container/Container.php
framework/packages/core/foundation/src/Container/ContainerBuilder.php
framework/packages/core/foundation/config/foundation.php
framework/packages/core/foundation/config/rules.php
framework/packages/core/foundation/README.md

docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md
docs/ssot/di-tags-and-middleware-ordering.md
```

Potential future files, depending on the accepted design:

```text
framework/packages/core/foundation/src/Container/Autowire/ContainerAutowirePlan.php
framework/packages/core/foundation/src/Container/Autowire/ContainerAutowireMetadata.php
framework/packages/core/foundation/src/Container/Autowire/ContainerAutowirePlanLoader.php
```

### Candidate tests

A future implementation would need a test matrix covering at least:

```text
reflection enabled + metadata absent
reflection disabled + metadata present
reflection disabled + metadata absent
metadata references unknown dependency
metadata references interface
metadata references abstract class
metadata order is deterministic
compiled metadata does not leak constructor data in diagnostics
compiled metadata does not depend on filesystem traversal order
compiled metadata does not depend on environment-specific values
```

Candidate test files may include:

```text
framework/packages/core/foundation/tests/Unit/ContainerConcreteAutowireRequiresBothFlagsTest.php
framework/packages/core/foundation/tests/Integration/ContainerAutowireUsesCompiledMetadataWhenReflectionIsDisabledTest.php
framework/packages/core/foundation/tests/Integration/ContainerAutowireRejectsMissingCompiledMetadataTest.php
framework/packages/core/foundation/tests/Contract/ContainerAutowireMetadataIsDeterministicContractTest.php
framework/packages/core/foundation/tests/Contract/ContainerAutowireMetadataDoesNotLeakDiagnosticsContractTest.php
```

### Why not now

The current Foundation container intentionally supports only conservative reflection-based concrete-class autowiring.

The existing two-flag model reserves a future architectural boundary, but the no-reflection autowire path is not implemented yet.

Implementing it now would expand the current cleanup scope into:

- metadata schema design;
- artifact or provider metadata ownership;
- runtime loader design;
- failure taxonomy;
- deterministic metadata tests;
- diagnostics safety tests;
- config rule updates;
- README, ADR, and SSoT updates;
- possible gate or artifact drift checks.

That is larger than a local runtime-boundary cleanup.

For now, the active behavior should remain:

```text
autowire_concrete = true
allow_reflection_for_concrete = true
→ reflection-based concrete autowire is allowed

any other combination
→ concrete autowire is disabled
```

### Promotion condition

Promote only through a numbered epic, ADR, or SSoT update.

Promotion is appropriate when one of the following happens:

- Coretsia introduces a compiled container or compiled service metadata artifact;
- runtime reflection needs to be disabled for a supported production mode;
- package compliance needs to verify no runtime reflection is used for concrete autowiring;
- container autowire behavior needs to support metadata generated by module planning or build tooling;
- long-running runtime modes require stricter boot-time/runtime separation around reflection.

### Possible future epic shape

```text
1.xxx.0 Foundation: Compiled Autowire Metadata
```

Potential deliverables:

- define the canonical compiled constructor metadata shape;
- decide whether metadata is generated artifact, provider metadata, or both;
- introduce a value object such as `ContainerAutowirePlan` if needed;
- split `canAutowire()` into reflection and metadata paths;
- split `autowire()` into reflection and metadata resolution paths;
- define deterministic failure reasons for missing or invalid metadata;
- ensure diagnostics never expose constructor argument values or raw reflection dumps;
- add tests for all autowire/reflection/metadata flag combinations;
- update Foundation config rules;
- update Foundation README;
- update the DI container/tag SSoT;
- record the decision in an ADR if the runtime surface changes.

---
