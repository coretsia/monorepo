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

## Contents

- [Foundation compiled autowire metadata](#foundation-compiled-autowire-metadata)
- [ModulePlan artifact for pre-container runtime entrypoint guard](#moduleplan-artifact-for-pre-container-runtime-entrypoint-guard)
- [Bounded ErrorDescriptor extension construction](#bounded-errordescriptor-extension-construction)

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

## ModulePlan artifact for pre-container runtime entrypoint guard

- Status: candidate
- Source epic: `1.350.0 Core Kernel: Runtime Drivers / Runtime Entrypoint Guard`
- Owner area: `core/kernel`, `platform/worker`
- Priority: later cleanup
- Type: artifact runtime boot / module-plan artifact / runtime entrypoint boundary

### Goal

Move runtime entrypoint compatibility validation in artifact-only production boot to happen before compiled container construction.

The current practical worker child boundary is:

```text
ArtifactRuntimeBooter::boot()
    builds runtime container from config.php + container.php artifacts

bin/coretsia-worker
    resolves RuntimeEntrypointGuard, ConfigRepositoryInterface, and ModulePlan
    from the built runtime container

RuntimeEntrypointGuard
    runs before WorkerPoolSpec, ApplicationWorker, KernelRuntime, and task execution
```

This is acceptable for the current worker-local executable boundary because runtime execution is still blocked before worker spec resolution and task execution.

However, it is not an absolute pre-container-build guard.

A future cleanup may introduce a dedicated `module-plan.php` artifact so that `ArtifactRuntimeBooter` can run `RuntimeEntrypointGuard` before calling `CompiledContainerFactory::build()`.

### Candidate policy

An absolute pre-container runtime entrypoint guard requires both:

```text
config.php artifact
module-plan.php artifact
```

before:

```text
container.php artifact
```

The future artifact-only boot sequence should be:

```text
ArtifactRuntimeBooter
    reads config.php
    reads module-plan.php
    hydrates ModulePlan
    runs RuntimeEntrypointGuard
    only then builds container.php
```

The target API shape may become:

```php
public function boot(
    string $configArtifactPath,
    string $modulePlanArtifactPath,
    string $containerArtifactPath,
): ContainerInterface
```

This is preferred over:

```php
public function boot(
    string $configArtifactPath,
    string $containerArtifactPath,
    ModulePlan $modulePlan,
): ContainerInterface
```

because `ArtifactRuntimeBooter` should remain an artifact-only production runtime boot facade. Callers should provide artifact paths, not prehydrated runtime objects.

### Candidate artifact shape

`ModulePlan` already exposes a stable exported scalar shape through `ModulePlan::toArray()`.

A future module-plan artifact should preserve the canonical top-level shape:

```text
app
disabled
enabled
modules
optionalMissing
preset
schemaVersion
topologicalOrder
warnings
```

The accepted artifact envelope name, schema version, payload validation rules, and hydration behavior must be defined by a numbered epic, ADR, or SSoT update before implementation.

The module-plan artifact MUST NOT contain:

- source config payloads;
- runtime services;
- service instances;
- closures;
- resources;
- filesystem handles;
- raw Composer payloads;
- raw preset payloads;
- provider class dumps;
- environment-specific values;
- absolute local paths;
- nondeterministic ordering.

### Candidate implementation shape

A future implementation would likely require one of:

```text
ModulePlan::fromArray()
```

or a dedicated hydrator such as:

```text
ModulePlanHydrator
ModulePlanArtifactReader
```

The artifact boot path should not run source module discovery as a fallback.

A conceptual future implementation shape:

```php
public function boot(
    string $configArtifactPath,
    string $modulePlanArtifactPath,
    string $containerArtifactPath,
): ContainerInterface {
    $reader = new PhpArtifactReader();
    $validator = new ArtifactSchemaValidator();

    $configPayload = self::readConfigPayload(
        reader: $reader,
        validator: $validator,
        configArtifactPath: $configArtifactPath,
    );

    $modulePlan = self::readModulePlan(
        reader: $reader,
        validator: $validator,
        modulePlanArtifactPath: $modulePlanArtifactPath,
    );

    self::assertRuntimeEntrypointAllowed(
        configPayload: $configPayload,
        modulePlan: $modulePlan,
    );

    return new CompiledContainerFactory(
        artifactReader: $reader,
        schemaValidator: $validator,
    )->build(
        containerArtifactPath: $containerArtifactPath,
        configPayload: $configPayload,
    );
}
```

### Worker child process shape

The worker child executable should receive a module-plan artifact path, not a serialized ModulePlan payload.

Future worker child arguments may become:

```text
--coretsia-worker-config=var/cache/worker/config.php
--coretsia-worker-module-plan=var/cache/worker/module-plan.php
--coretsia-worker-container=var/cache/worker/container.php
```

Do not pass ModulePlan data through argv as JSON, base64, serialized PHP, or any other raw payload encoding.

Command-line payload transfer is not acceptable because:

- command lines may be visible in process lists;
- command lines may be captured by logs or diagnostics;
- it duplicates artifact-layer responsibility;
- it bypasses the deterministic artifact boundary;
- it increases leakage risk for runtime payload data.

### Candidate files

```text
framework/packages/core/kernel/src/Boot/ArtifactRuntimeBooter.php
framework/packages/core/kernel/src/Module/ModulePlan.php
framework/packages/core/kernel/src/Module/ModulePlanEntry.php
framework/packages/core/kernel/src/Runtime/Entrypoint/RuntimeEntrypointGuard.php
framework/packages/core/kernel/src/Config/ArrayConfigRepository.php
framework/packages/core/kernel/src/Artifacts/Builders/ModuleManifestBuilder.php
framework/packages/core/kernel/src/Artifacts/Compiler/ArtifactCompiler.php
framework/packages/core/kernel/src/Artifacts/Verifier/CacheVerifier.php

framework/packages/platform/worker/src/Provider/WorkerServiceProvider.php
framework/packages/platform/worker/src/Provider/WorkerServiceFactory.php
framework/packages/platform/worker/bin/coretsia-worker
```

Potential future files, depending on the accepted design:

```text
framework/packages/core/kernel/src/Artifacts/Builders/ModulePlanBuilder.php
framework/packages/core/kernel/src/Artifacts/Runtime/ModulePlanArtifactReader.php
framework/packages/core/kernel/src/Module/ModulePlanHydrator.php
framework/packages/core/kernel/src/Module/Exception/ModulePlanHydrationException.php
```

If worker process-driver wiring owns the child command vector at that time, likely candidate files may also include:

```text
framework/packages/platform/worker/src/Manager/Driver/ProcWorkerManagerDriver.php
```

### Candidate tests

A future implementation would need a test matrix covering at least:

```text
ArtifactRuntimeBooter rejects non-classic HTTP driver without platform.http before container.php is read
ArtifactRuntimeBooter allows non-classic HTTP driver when platform.http is present in module-plan artifact
ArtifactRuntimeBooter allows classic HTTP without platform.http
ArtifactRuntimeBooter rejects missing module-plan artifact deterministically
ArtifactRuntimeBooter rejects invalid module-plan artifact deterministically
ArtifactRuntimeBooter rejects module-plan schema version drift deterministically
ArtifactRuntimeBooter does not run source module discovery
ArtifactRuntimeBooter does not resolve ModulePlan from runtime container
ArtifactRuntimeBooter does not build container.php before runtime entrypoint guard passes
worker child passes module-plan artifact path, not serialized ModulePlan payload
worker child rejects missing --coretsia-worker-module-plan argument
worker child diagnostics do not expose raw paths, payloads, argv dumps, or artifact contents
```

Candidate test files may include:

```text
framework/packages/core/kernel/tests/Integration/ArtifactRuntimeBooterRunsEntrypointGuardBeforeContainerBuildTest.php
framework/packages/core/kernel/tests/Integration/ArtifactRuntimeBooterRejectsMissingModulePlanArtifactTest.php
framework/packages/core/kernel/tests/Integration/ArtifactRuntimeBooterRejectsInvalidModulePlanArtifactTest.php
framework/packages/core/kernel/tests/Contract/ModulePlanArtifactShapeContractTest.php
framework/packages/core/kernel/tests/Contract/ModulePlanHydratorContractTest.php

framework/packages/platform/worker/tests/Contract/WorkerChildRequiresModulePlanArtifactArgumentTest.php
framework/packages/platform/worker/tests/Integration/WorkerChildBootRunsEntrypointGuardBeforeWorkerSpecTest.php
```

### Why not now

The current cleanup strengthened runtime entrypoint validation without expanding the artifact model.

Implementing absolute pre-container validation now would require:

- defining a module-plan artifact envelope;
- defining schema versioning for the module-plan artifact;
- adding a canonical ModulePlan hydration path;
- validating ModulePlan artifact payloads;
- deciding artifact naming and cache paths;
- updating artifact compiler output;
- updating cache verification;
- updating worker child argv shape;
- updating proc worker command construction;
- updating artifact boot tests;
- updating diagnostics taxonomy;
- updating docs, ADR, or SSoT policy.

That is larger than the current runtime-boundary cleanup.

The current worker child guard remains acceptable because it blocks execution before:

```text
WorkerPoolSpec
ApplicationWorker
KernelRuntime
task execution
```

The normal parent `worker:start` path already runs `RuntimeEntrypointGuard` before worker pool start.

### Current accepted behavior

For now, keep the practical worker child boundary:

```text
ArtifactRuntimeBooter::boot()
    builds runtime container

bin/coretsia-worker
    resolves RuntimeEntrypointGuard, ConfigRepositoryInterface, ModulePlan
    runs RuntimeEntrypointGuard
    resolves WorkerPoolSpec
    resolves ApplicationWorker
    runs worker
```

Do not introduce a partial solution by passing `ModulePlan` object directly into `ArtifactRuntimeBooter::boot()`.

Do not serialize ModulePlan through argv.

### Promotion condition

Promote only through a numbered epic, ADR, or SSoT update.

Promotion is appropriate when one of the following happens:

- Coretsia introduces a dedicated module-plan artifact;
- artifact-only production boot must validate runtime entrypoint compatibility before compiled container construction;
- long-running runtime modes require stricter artifact/runtime separation;
- worker child boot must be fully artifact-path-only before container build;
- package compliance needs to verify that artifact runtime boot does not resolve ModulePlan from runtime container;
- cache verification needs to include module-plan artifact drift;
- runtime adapters need a uniform artifact-only pre-container guard.

### Possible future epic shape

```text
1.xxx.0 Kernel: ModulePlan Artifact for Pre-Container Runtime Entrypoint Guard
```

Potential deliverables:

- define the canonical module-plan artifact envelope and schema version;
- introduce `ModulePlanBuilder` or equivalent artifact builder;
- introduce `ModulePlanHydrator` or `ModulePlan::fromArray()`;
- introduce `ModulePlanArtifactReader` if artifact reading should stay separate;
- update `ArtifactRuntimeBooter::boot()` to accept `modulePlanArtifactPath`;
- run `RuntimeEntrypointGuard` before `CompiledContainerFactory::build()`;
- update worker child argv shape to include `--coretsia-worker-module-plan`;
- update proc worker command construction;
- update artifact compiler and cache verifier;
- ensure diagnostics never expose raw paths, argv dumps, artifact payloads, or module-plan payload dumps;
- add tests proving container.php is not read before entrypoint guard passes;
- update relevant ADR and SSoT documents.

---

## Bounded ErrorDescriptor extension construction

- Status: candidate
- Source epic: `1.90.0` / ErrorDescriptor extensions resource-bound follow-up
- Owner area: `core/contracts`, future error mapper and integration owners
- Priority: later cleanup
- Type: error normalization / bounded metadata construction / producer resource boundary

### Goal

Introduce a centralized bounded construction path for `ErrorDescriptor.extensions` if future mapper and integration APIs begin producing extension metadata regularly from potentially large or externally controlled sources.

The current `ErrorDescriptor` implementation already protects its own normalization boundary with fixed limits for:

```text
container depth
node count
individual string bytes
aggregate string bytes
recursive PHP-array traversal
```

This correctly prevents unbounded work inside `ErrorDescriptor`.

However, that boundary cannot retroactively prevent memory already allocated by a producer before descriptor construction.

For example:

```php
$huge = buildVeryLargeArray();

new ErrorDescriptor(
    code: 'core.example',
    message: 'Example message.',
    extensions: $huge,
);
```

In this shape, the large PHP array is already materialized before `ErrorDescriptor::__construct()` begins.

A future cleanup may introduce a dedicated bounded extension-construction abstraction so that producers can build safe extension metadata incrementally instead of first materializing arbitrary arrays.

### Motivation

This cleanup becomes relevant when Coretsia has real mapper or integration APIs that regularly derive `ErrorDescriptor` extension metadata from sources such as:

```text
plugins
queue transports
HTTP runtimes
database adapters
external SDKs
callback-provided metadata
decoded transport structures
domain exception context
```

Without a shared bounded construction abstraction, every producer would otherwise need to implement its own:

```text
depth accounting
node accounting
string-size accounting
aggregate-size accounting
safe-key validation
deterministic map normalization
failure behavior
```

That would create duplicated policy and increase the risk of drift between integrations.

The desired long-term shape is:

```text
potentially unbounded source
        ↓
source-owner input limit
        ↓
incremental safe derivation
        ↓
bounded extension construction
        ↓
ErrorDescriptor
        ↓
canonical descriptor validation
```

This provides three distinct protection layers:

```text
1. source-owner limits
   prevent excessive source materialization

2. bounded extension construction
   prevents producer-side extension growth

3. ErrorDescriptor validation
   protects the canonical contracts boundary
```

### Candidate policy

The future abstraction MUST NOT imply that `ErrorDescriptor` can protect against memory already consumed before the abstraction is invoked.

Potentially unbounded source data MUST be bounded before or during materialization by the owner of that source.

Examples include:

```text
HTTP owner
→ body / decoded input size limit

queue adapter
→ message / metadata size limit

database adapter
→ bounded diagnostic derivation

plugin owner
→ bounded plugin metadata contract

external SDK adapter
→ bounded extraction from vendor result/error objects
```

The extension-construction abstraction should operate only on data that can be consumed incrementally or whose source boundary is already controlled.

It MUST NOT encourage the pattern:

```text
materialize arbitrary source completely
→ pass huge array to bounded builder
```

because the memory cost has already occurred before the builder can enforce its limits.

### Candidate implementation shape

A future implementation may introduce a dedicated value object or builder such as:

```text
ErrorDescriptorExtensions
```

or:

```text
ErrorDescriptorExtensionsBuilder
```

The actual class name and public API must be decided by the promoting epic.

A conceptual value-object shape could be:

```php
final readonly class ErrorDescriptorExtensions
{
    public static function empty(): self;

    /**
     * @param iterable<string,mixed> $entries
     */
    public static function fromIterable(iterable $entries): self;

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array;
}
```

A builder-oriented API may instead expose incremental operations such as:

```php
$extensions = $builder
    ->add('operation', 'consume')
    ->add('outcome', 'rejected')
    ->add('violationCount', 3)
    ->build();
```

The accepted design should preserve the existing canonical `ErrorDescriptor` invariants:

```text
safe json-like values
deterministic map ordering
list ordering preservation
forbidden semantic extension channels
absolute-local-path rejection
fixed resource limits
fail-closed behavior
no automatic truncation or redaction
```

The builder or value object MUST NOT become a generic payload sanitization facility.

It MUST NOT accept raw secrets, SQL, transport payloads, throwable dumps, request bodies, response bodies, credentials, tokens, or arbitrary application structures merely to sanitize them later.

Producer-side safe derivation remains mandatory.

### Iterable boundary

Using `iterable` may allow incremental consumption from generators or other lazy sources:

```php
function extensionEntries(): iterable
{
    yield 'operation' => 'consume';
    yield 'outcome' => 'rejected';
    yield 'violationCount' => 3;
}
```

This can avoid requiring a complete root extension array before validation.

However, `iterable` alone is not a complete memory-safety guarantee because PHP arrays are also iterable.

This remains possible:

```php
$huge = buildVeryLargeArray();

ErrorDescriptorExtensions::fromIterable($huge);
```

Therefore the future contract MUST explicitly distinguish:

```text
bounded incremental construction
```

from:

```text
retroactive validation of an already-materialized oversized structure
```

Only the first can prevent producer-side extension allocation growth.

### Source-owner responsibility

The strongest supported model should be:

```text
external or arbitrary source
        ↓
source-specific size/resource limit
        ↓
safe owner-approved derivation
        ↓
bounded ErrorDescriptor extension construction
        ↓
ErrorDescriptor canonical validation
```

For encoded or transport data, limits should be applied before expensive decoding where feasible.

For example:

```text
raw HTTP body
→ body-size limit
→ decode
→ derive safe compact metadata
→ bounded extensions
```

rather than:

```text
raw HTTP body
→ decode unbounded payload
→ copy decoded structure into extensions
→ reject later
```

Likewise, an adapter should prefer:

```text
vendor exception/result
→ stable reason
→ safe count
→ safe length
→ non-reconstructable hash when permitted
```

instead of copying an entire vendor-provided diagnostic structure.

### Candidate files

Potential existing files:

```text
framework/packages/core/contracts/src/Observability/Errors/ErrorDescriptor.php
framework/packages/core/contracts/src/Observability/Errors/ExceptionMapperInterface.php

framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreJsonLikeContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreBoundedContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsEnforceRedactionContractTest.php

docs/ssot/error-descriptor.md
docs/ssot/errors-boundary.md
docs/ssot/observability-and-errors.md
```

Potential future files, depending on the accepted design:

```text
framework/packages/core/contracts/src/Observability/Errors/ErrorDescriptorExtensions.php
```

or:

```text
framework/packages/core/contracts/src/Observability/Errors/ErrorDescriptorExtensionsBuilder.php
```

If implementation-specific construction behavior should remain outside `core/contracts`, a future epic may instead place the builder in the runtime package that owns error normalization while keeping the resulting value contract Contracts-owned.

That ownership decision must be made before implementation.

### Candidate tests

A future implementation should cover at least:

```text
incremental construction accepts safe metadata

incremental construction preserves deterministic map ordering

incremental construction preserves semantic list ordering

construction rejects the 257th node before consuming later entries

construction rejects container depth greater than the canonical limit

construction rejects an individual string exceeding the canonical limit

construction rejects aggregate string bytes exceeding the canonical limit

construction rejects forbidden semantic keys

construction rejects absolute local paths

construction does not auto-truncate oversized values

construction does not auto-drop entries

construction failure diagnostics contain no raw values

generator-backed input stops being consumed immediately after budget failure

entries after a budget violation are not evaluated

ErrorDescriptor accepts the resulting bounded extension value

existing ErrorDescriptor array behavior remains compatible or has an explicitly versioned migration path

source-owner tests prove large transport/plugin/vendor payloads are bounded before conversion into extension metadata
```

Potential test files may include:

```text
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsBuilderIsBoundedContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsBuilderIsDeterministicContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsBuilderPreservesRedactionContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsBuilderConsumesIterableIncrementallyContractTest.php
```

The exact test names depend on the accepted implementation shape.

### Why not now

The current ErrorDescriptor resource-bound hardening already closes the active availability defect inside `ErrorDescriptor` normalization.

The current implementation guarantees that recursive, excessively deep, excessively wide, or oversized extension structures do not cause unbounded normalization work inside the descriptor.

Introducing a dedicated extension value object or builder now would expand that fix into a public API and producer architecture change.

That would require decisions about:

```text
public API shape
value object versus builder
Contracts versus runtime ownership
array backward compatibility
iterable semantics
migration strategy
mapper integration
plugin integration
queue adapter integration
HTTP runtime integration
database adapter integration
external SDK adapter integration
source-owner resource limits
```

Those decisions are not required for the current ErrorDescriptor resource-bound fix.

There is also currently limited value in centralizing producer-side construction while concrete mapper and integration APIs do not yet use `ErrorDescriptor.extensions` extensively.

For now, the accepted behavior remains:

```text
producer
→ MUST derive safe, bounded metadata

ErrorDescriptor
→ independently validates and bounds normalization

already-materialized producer memory
→ remains producer/source-owner responsibility
```

### Promotion condition

Promote only through a numbered epic, ADR, or SSoT update.

Promotion becomes appropriate when one or more of the following conditions occur:

- multiple concrete exception mappers begin producing non-empty `ErrorDescriptor.extensions`;
- plugin APIs can contribute error extension metadata;
- queue transports regularly derive descriptor metadata from broker messages or failure objects;
- HTTP runtimes regularly derive descriptor metadata from request/response/runtime state;
- database adapters regularly derive descriptor metadata from database failures;
- external SDK integrations regularly derive descriptor metadata from vendor exceptions or result objects;
- two or more packages duplicate equivalent extension depth/node/string budget logic;
- producer-side array materialization becomes a measurable memory or availability concern;
- an incremental extension construction API is needed to stop consuming generators or lazy sources immediately after the canonical budget is exhausted;
- package compliance requires a single enforceable producer-side bounded extension construction contract.

### Possible future epic shape

```text
1.xxx.0 Contracts: Bounded ErrorDescriptor Extension Construction
```

Potential deliverables:

- decide whether the canonical abstraction is a value object, builder, or another bounded construction model;
- define ownership between `core/contracts` and runtime error-normalization packages;
- define incremental `iterable` consumption semantics;
- define whether existing `array $extensions` construction remains supported and for how long;
- preserve the current canonical depth, node, individual-string, and aggregate-string limits;
- preserve semantic-key and absolute-path rejection;
- preserve deterministic recursive map ordering;
- preserve semantic list ordering;
- ensure construction stops immediately when the resource budget is exhausted;
- ensure lazy producers are not consumed after failure;
- ensure no implementation silently truncates, drops, masks, or repairs producer metadata;
- define safe failure diagnostics;
- add producer-side integration guidance for plugins, queues, HTTP runtimes, database adapters, and external SDKs;
- add contract and integration tests for bounded incremental construction;
- update `ErrorDescriptor` SSoT;
- update the errors boundary SSoT;
- record an ADR only if the accepted solution changes the public error-normalization architecture.

---
