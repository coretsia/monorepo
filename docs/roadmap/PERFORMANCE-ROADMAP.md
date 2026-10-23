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

## Independent Performance Roadmap

Rules:

- Future capabilities MUST be introduced through explicit Performance Roadmap epics or reviewed extensions of existing performance epics.
- Shared functionality MUST be introduced only when a concrete consumer requires it.
- New capabilities MUST NOT silently alter existing protocol semantics, baseline identity, or comparison behavior.
- The benchmark toolkit MUST remain a bounded tooling library rather than becoming a general-purpose utility framework.
- The absence of deferred capabilities MUST NOT prevent completion of `PERF.10.0`.

### PERF.10.0 Benchmark Toolkit Foundation (MUST) [TOOLING]

---
type: package
phase: PERF
track: performance
epic_id: "PERF.10.0"
owner_path: "packages/devtools/benchmark-toolkit/"

package_id: "devtools/benchmark-toolkit"
composer: "coretsia/devtools-benchmark-toolkit"
kind: library

execution_policy: deferred
required_for_product_release: false
activation: explicit
eligible_after:
- "1.50.0"
- "1.60.0"
- "1.265.0"

goal: "Establish a reusable tooling-only benchmark foundation for deterministic measurement processing, canonical baseline representation, observable runtime identity, scenario fingerprinting, and regression comparison without introducing benchmark execution infrastructure or blocking product development."

provides:
- "Reusable benchmark measurement and comparison primitives"
- "Canonical baseline serialization and validation primitives"
- "Observable PHP runtime identity and configuration fingerprinting"
- "Deterministic scenario fingerprinting and protocol identity"
- "Integer-only statistical processing and regression comparison"
- "Shared tooling contracts for future CLI, HTTP, Core, IO, and asynchronous benchmark suites"
- "Explicit separation between benchmark toolkit, benchmark execution, CI orchestration, and production runtime"
- "Independent deferred performance development without product release dependencies"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Performance Roadmap Policy (MUST)

#### Independent execution policy

- [ ] `PERF.10.0` belongs exclusively to the independent Performance Roadmap.
- [ ] The epic MUST NOT be a prerequisite for completing any product phase.
- [ ] The epic MUST NOT block development of:
  - [ ] Core contracts, Foundation, or Kernel.
  - [ ] Platform CLI and mode infrastructure.
  - [ ] Micro, Express, Hybrid, or Enterprise release modes.
  - [ ] Production package implementations.
- [ ] The epic MAY be implemented at any time after its declared eligibility prerequisites are satisfied.
- [ ] Activation MUST require an explicit development decision.
- [ ] The absence of this package MUST NOT be treated as a product implementation failure.
- [ ] Product CI, quality gates, and architecture gates MUST NOT depend on this package before its implementation.
- [ ] Once implemented and integrated into the monorepo, the package MUST comply with existing package, dependency, quality, and security policies.
- [ ] The package MAY become a prerequisite for subsequent Performance Roadmap epics.
- [ ] Performance Roadmap prerequisites MUST NOT create reverse dependencies from production packages to benchmark tooling.
- [ ] `MUST` requirements within this epic become binding when its implementation is activated; they do not make its activation mandatory.

#### Scope and ownership

- [ ] This epic owns the shared benchmark tooling foundation only.
- [ ] This epic MUST NOT implement complete CLI, HTTP, Core, database, filesystem, or asynchronous benchmark suites.
- [ ] This epic MUST NOT introduce a universal benchmark process executor.
- [ ] This epic MUST NOT provision dedicated benchmark hardware, virtual machines, or GitHub self-hosted runners.
- [ ] This epic MUST NOT establish authoritative performance baselines.
- [ ] This epic MUST NOT enforce wall-clock regression thresholds against production code.
- [ ] This epic MUST NOT introduce mandatory performance verification into generic CI.
- [ ] Benchmark execution, measurement protocols, fixtures, committed baselines, and CI gating remain responsibilities of their respective future performance epics.
- [ ] The shared foundation MUST support incremental adoption without forcing all future benchmark suites to use identical measurement protocols.

---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `0.40.0` — deterministic devtools/internal-toolkit primitives exist.
  - `0.100.0` — Composer workspace and managed repository synchronization infrastructure exist.
  - `1.50.0` — repository tooling baseline, architecture rails, and canonical gate infrastructure exist.
  - `1.60.0` — package compliance infrastructure exists.
  - `1.265.0` — repository-wide release-line versioning and package publish-safety policy exist.

- Required deliverables (exact paths):
  - `composer.json` — repository-root Composer workspace and dependency declarations.
  - `composer.lock` — canonical committed dependency resolution.
  - `packages/devtools/internal-toolkit/composer.json` — existing tooling library dependency.
  - `packages/devtools/internal-toolkit/src/Json.php` — canonical stable tooling JSON encoding.
  - `tools/support/GateRuntime.php` — existing tooling execution and failure boundary.
  - `tools/support/DeterministicFile.php` — canonical deterministic filesystem access.
  - `tools/support/RepositoryContext.php` — canonical repository path resolution.
  - `tools/support/ErrorCodes.php` — canonical tooling error-code registry.
  - `tools/build/new-package.php` — package creation infrastructure.
  - `tools/build/sync_composer_repositories.php` — managed Composer repository synchronization.
  - `tools/build/package_index.php` — package catalog generation.
  - `tools/build/deptrac_generate.php` — dependency rules generation.
  - `tools/testing/package-index.php` — generated package catalog.
  - `tools/testing/deptrac.yaml` — generated architecture dependency configuration.

- Required config roots/keys:
  - none — the benchmark toolkit is tooling-only and MUST NOT introduce application configuration roots.

- Required tags:
  - none.

- Required contracts / ports:
  - `Coretsia\Devtools\InternalToolkit\Json` — stable tooling JSON encoding.
  - No production contracts are required.

- Environment prerequisites:
  - PHP 8.4 or newer within the version constraints supported by the repository.
  - 64-bit PHP for integer-only benchmark arithmetic.
  - Composer 2 and the repository-root dependency installation.
  - Existing monorepo PHPUnit and architecture validation infrastructure.
  - No dedicated benchmark runner is required for implementation or testing of this epic.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `devtools/internal-toolkit`

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`
- `presets/*`
- `enterprise/*`
- `applications/*`
- `coretsia/framework`
- `coretsia/skeleton`
- direct imports of production Foundation, Kernel, or Platform implementations
- direct dependencies on repository-local `tools/support/*` classes from the package source
- runtime service-container and module-discovery integration
- production observability or profiling implementations
- hard dependencies on external benchmark services or SaaS platforms
- benchmark execution frameworks not explicitly required by this epic
- independent implementations of existing deterministic tooling primitives

#### Dependency direction (MUST)

- [ ] `devtools/benchmark-toolkit` MAY depend on `devtools/internal-toolkit`.
- [ ] Repository tooling and future performance suites MAY consume `devtools/benchmark-toolkit`.
- [ ] Production packages MUST NOT depend on `devtools/benchmark-toolkit`.
- [ ] The benchmark toolkit MUST NOT depend on individual benchmark suites.
- [ ] The benchmark toolkit MUST NOT depend on repository `tools/gates/*` implementations.
- [ ] Future benchmark gates MUST remain responsible for integration with the existing `GateRuntime` boundary.
- [ ] The dependency graph MUST remain acyclic.

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - N/A.

- Contracts:
  - N/A — no production-facing benchmark ports are introduced.

- Tooling stable APIs:
  - `Coretsia\Devtools\InternalToolkit\Json::encodeStable()`

- Repository tooling integration boundaries:
  - `Coretsia\Tools\Support\GateRuntime` — consumed by future gate adapters, not by benchmark toolkit source.
  - `Coretsia\Tools\Support\DeterministicFile` — consumed by future baseline file adapters, not by benchmark toolkit source.
  - `Coretsia\Tools\Support\RepositoryContext` — consumed by future repository-aware benchmark entrypoints, not by benchmark toolkit source.

---

### Entry points / integration points (MUST)

- Package API:
  - `Coretsia\Devtools\BenchmarkToolkit\Statistics\IntegerMedian`
  - `Coretsia\Devtools\BenchmarkToolkit\Comparison\DurationRegression`
  - `Coretsia\Devtools\BenchmarkToolkit\Identity\PhpRuntimeIdentity`
  - `Coretsia\Devtools\BenchmarkToolkit\Fingerprint\ScenarioFingerprint`
  - `Coretsia\Devtools\BenchmarkToolkit\Protocol\ProtocolIdentity`
  - `Coretsia\Devtools\BenchmarkToolkit\Baseline\CanonicalBaselineJson`

- CLI:
  - N/A — this epic MUST NOT introduce benchmark CLI commands.

- Composer:
  - The package MUST be installed as a root `require-dev` dependency.
  - The package MUST be available through the existing monorepo Composer autoloader.
  - Existing Composer quality, test, package compliance, and architecture commands MUST remain canonical.
  - No new benchmark execution command is introduced.

- CI:
  - Existing generic CI MAY execute the package's deterministic unit and contract tests.
  - CI MUST NOT execute real performance measurements as part of this epic.
  - CI MUST NOT require a pinned benchmark runner for this package.
  - No new mandatory performance workflow is introduced.

- HTTP:
  - N/A.

- Kernel hooks/tags:
  - N/A.

- Other runtime discovery/integration tags:
  - N/A.

- Artifacts:
  - reads: N/A.
  - writes: N/A.

- Notes:
  - This package provides reusable PHP primitives, not executable benchmark suites.
  - Future suites own their benchmark targets, measurement protocol, runner selection, baseline files, and regression-gating entrypoints.
  - The package MUST NOT introduce runtime module registration or application service-provider wiring.
  - No production or runtime artifact may depend on benchmark toolkit output.

---

### Deliverables (MUST)

#### Creates

- [ ] `packages/devtools/benchmark-toolkit/composer.json` — tooling-only Composer package manifest.
- [ ] `packages/devtools/benchmark-toolkit/LICENSE` — package license.
- [ ] `packages/devtools/benchmark-toolkit/NOTICE` — package attribution notice.
- [ ] `packages/devtools/benchmark-toolkit/SECURITY.md` — package security guidance aligned with the repository policy.
- [ ] `packages/devtools/benchmark-toolkit/README.md` — public package scope, API, dependency boundaries, methodology, and usage guidance.

- [ ] `packages/devtools/benchmark-toolkit/src/Statistics/IntegerMedian.php` — deterministic integer-only median calculation.
- [ ] `packages/devtools/benchmark-toolkit/src/Comparison/DurationRegression.php` — exact integer-only duration regression comparison.
- [ ] `packages/devtools/benchmark-toolkit/src/Identity/PhpRuntimeIdentity.php` — observable PHP runtime identity and configuration fingerprinting.
- [ ] `packages/devtools/benchmark-toolkit/src/Fingerprint/ScenarioFingerprint.php` — canonical scenario fingerprint calculation.
- [ ] `packages/devtools/benchmark-toolkit/src/Protocol/ProtocolIdentity.php` — immutable benchmark protocol identity and version validation.
- [ ] `packages/devtools/benchmark-toolkit/src/Baseline/CanonicalBaselineJson.php` — canonical JSON baseline serialization and validation without filesystem ownership.

- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Statistics/IntegerMedianTest.php`
- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Comparison/DurationRegressionTest.php`
- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Identity/PhpRuntimeIdentityTest.php`
- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Fingerprint/ScenarioFingerprintTest.php`
- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Protocol/ProtocolIdentityTest.php`
- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Baseline/CanonicalBaselineJsonTest.php`

- [ ] `packages/devtools/benchmark-toolkit/tests/Contract/ToolingIsolationTest.php` — package boundary and forbidden-dependency verification.
- [ ] `packages/devtools/benchmark-toolkit/tests/Contract/FailureOutputSafetyTest.php` — safe failure contracts for malformed benchmark data.
- [ ] `packages/devtools/benchmark-toolkit/tests/Integration/BenchmarkToolkitIntegrationTest.php` — deterministic synthetic integration of the foundation primitives.

- [ ] `docs/benchmarks/methodology.md` — shared benchmark methodology, evidence boundaries, baseline lifecycle, and suite ownership policy.

#### Modifies

- [ ] `composer.json`:
  - [ ] register `coretsia/devtools-benchmark-toolkit` in root `require-dev`.
  - [ ] preserve the existing versioning and root dependency constraints.
  - [ ] register the package path through the existing managed Composer repository synchronization mechanism.
  - [ ] MUST NOT introduce the package into production `require`.
  - [ ] MUST NOT add benchmark execution to `gates` or `ci`.

- [ ] `composer.lock`:
  - [ ] update the committed lockfile to include the new tooling package.
  - [ ] use the existing root dependency resolver and repository-wide release-line policy.
  - [ ] MUST NOT introduce an independent lockfile for benchmark tooling.

- [ ] `tools/testing/package-index.php`:
  - [ ] regenerate through the canonical package-index generator.
  - [ ] include the new `devtools/benchmark-toolkit` library package.
  - [ ] MUST NOT edit generated entries manually.

- [ ] `tools/testing/deptrac.yaml`:
  - [ ] regenerate through the canonical Deptrac generator.
  - [ ] include the new benchmark toolkit namespace and package layer.
  - [ ] enforce the declared package dependency direction.
  - [ ] MUST NOT edit generated dependency rules manually.

#### Package skeleton (type=package)

- [ ] `composer.json`:
  - [ ] `name` = `coretsia/devtools-benchmark-toolkit`.
  - [ ] `type` = `library`.
  - [ ] `license` = `Apache-2.0`.
  - [ ] `require.php` = `^8.4`.
  - [ ] `require.coretsia/devtools-internal-toolkit` = repository-approved matching release-line constraint.
  - [ ] `require.ext-json` = `*`.
  - [ ] `require.ext-hash` = `*`.
  - [ ] PSR-4 namespace `Coretsia\Devtools\BenchmarkToolkit\` → `src/`.
  - [ ] PSR-4 development namespace `Coretsia\Devtools\BenchmarkToolkit\Tests\` → `tests/`.
  - [ ] `extra.coretsia.kind` = `library`.
  - [ ] MUST NOT register runtime modules or service providers.
  - [ ] MUST NOT depend on production packages.
  - [ ] MUST NOT add an independent release-versioning mechanism.

- [ ] `README.md`:
  - [ ] Package identity and ownership.
  - [ ] Supported PHP version and dependencies.
  - [ ] Tooling-only lifecycle.
  - [ ] Public API and stable contracts.
  - [ ] Supported measurement units and numeric limitations.
  - [ ] Deterministic statistics and comparison rules.
  - [ ] Baseline serialization and validation responsibilities.
  - [ ] Runtime identity limitations.
  - [ ] Measurement protocol versioning.
  - [ ] Observability.
  - [ ] Errors.
  - [ ] Security / Redaction.
  - [ ] Explicit non-goals and future extension policy.

- Module:
  - N/A — library package.

- ServiceProvider:
  - N/A — no runtime or DI integration.

- Runtime configuration:
  - N/A — package MUST NOT introduce application config roots.

- Runtime cross-cutting noop tests:
  - N/A — tooling-only library, not a runtime module.

#### Shared measurement primitives (MUST)

##### `IntegerMedian`

- [ ] MUST be a final stateless class.
- [ ] MUST accept an ordered list of positive integer measurements.
- [ ] MUST support odd measurement counts from `3` through `31`, inclusive.
- [ ] MUST reject empty, even-length, oversized, and malformed sample collections.
- [ ] MUST reject zero, negative, float, numeric-string, or otherwise invalid measurements.
- [ ] MUST require 64-bit integer semantics.
- [ ] MUST return the exact middle integer after numerical sorting.
- [ ] MUST NOT calculate an arithmetic mean.
- [ ] MUST NOT perform floating-point conversion.
- [ ] MUST NOT depend on input ordering.
- [ ] MUST NOT access the clock, filesystem, environment, or network.
- [ ] MUST NOT own warmup execution or sample collection.
- [ ] Measurement-count and maximum-duration policies beyond the supported foundation bounds remain responsibilities of individual benchmark protocols.

##### `DurationRegression`

- [ ] MUST be a final stateless class.
- [ ] MUST accept:
  - [ ] current duration in positive integer microseconds.
  - [ ] baseline duration in positive integer microseconds.
  - [ ] threshold percentage as an integer.
- [ ] Threshold percentage MUST be validated within `100..1000`.
- [ ] MUST use exact integer arithmetic.
- [ ] MUST define degradation as:

  `current_us * 100 > baseline_us * threshold_percent`

- [ ] Equality with the threshold MUST NOT count as degradation.
- [ ] MUST reject invalid, non-positive, overflowing, or unsupported numeric inputs.
- [ ] MUST validate multiplication bounds before performing integer multiplication.
- [ ] MUST NOT silently clamp measurements.
- [ ] MUST NOT use float multiplication, division, or epsilon comparisons.
- [ ] MUST NOT introduce a default threshold.
- [ ] MUST NOT select benchmark policies automatically.
- [ ] MUST NOT assume that all future benchmark metrics use lower-is-better semantics.
- [ ] The initial implementation MUST support duration regression comparisons only.
- [ ] Higher-is-better metrics, throughput comparisons, and multi-metric policies MUST be introduced separately when required by a future performance epic.
- [ ] The class MUST return a comparison result without printing diagnostics or selecting a gate exit code.

##### `PhpRuntimeIdentity`

- [ ] MUST provide an immutable, canonical representation of observable PHP runtime identity.
- [ ] MUST collect the following fields from the actual executing PHP runtime:
  - [ ] `os_family: string`
  - [ ] `architecture: string`
  - [ ] `php_version_id: int`
  - [ ] `php_int_size: int`
  - [ ] `php_sapi: string`
  - [ ] `php_config_sha256: string`
- [ ] MAY include a caller-supplied validated `runner_class: string` in the canonical identity representation.
- [ ] `runner_class` MUST NOT be treated as proof of physical hardware identity.
- [ ] The initial canonical PHP configuration fingerprint input MUST contain:
  - [ ] `memory_limit`
  - [ ] `realpath_cache_size`
  - [ ] `opcache.enable_cli`
  - [ ] `opcache.jit`
  - [ ] `opcache.jit_buffer_size`
  - [ ] loaded PHP extension names and versions.
- [ ] PHP INI values MUST be obtained from the executing runtime through `ini_get()`.
- [ ] Loaded extension identities MUST be derived from the executing runtime.
- [ ] Extension names MUST be ordered by byte-wise `strcmp`.
- [ ] The configuration fingerprint MUST be calculated as:

  `hash('sha256', Json::encodeStable($validatedConfiguration))`

- [ ] The fingerprint MUST contain exactly 64 lowercase hexadecimal characters.
- [ ] The identity MUST NOT contain:
  - [ ] hostnames.
  - [ ] absolute executable paths.
  - [ ] absolute INI paths.
  - [ ] environment values.
  - [ ] credentials or secrets.
  - [ ] process identifiers.
  - [ ] timestamps.
  - [ ] machine-specific temporary paths.
- [ ] Identity comparison MUST use the complete validated canonical field set.
- [ ] Unknown and missing identity fields MUST be rejected by the corresponding strict validator.
- [ ] Unsupported PHP configurations MUST fail rather than produce a misleading compatible identity.
- [ ] The toolkit MUST NOT claim to verify dedicated physical CPU ownership.
- [ ] The toolkit MUST NOT infer CPU frequency stability, thermal stability, or hypervisor isolation.
- [ ] The toolkit MUST NOT treat parent PHP runtime observation as proof of the effective configuration of independently launched benchmark child processes.
- [ ] Future benchmark suites MUST own child-runtime invariants and trusted CI runner selection.
- [ ] Changes to fingerprinted configuration fields or fingerprint semantics MUST require review of affected measurement protocols and baselines.

##### `ScenarioFingerprint`

- [ ] MUST be a final stateless class.
- [ ] MUST calculate SHA-256 from a caller-validated deterministic scenario descriptor.
- [ ] Fingerprint input MUST consist only of canonical json-like arrays supported by `Json::encodeStable()`.
- [ ] Fingerprint calculation MUST use:

  `hash('sha256', Json::encodeStable($validatedDescriptor))`

- [ ] MUST preserve ordered list semantics.
- [ ] MUST preserve the caller's validated scenario identity and relevant configuration values.
- [ ] MUST reject unsupported scalar types through the canonical tooling JSON boundary.
- [ ] MUST return exactly 64 lowercase hexadecimal characters.
- [ ] MUST NOT derive fingerprints from reconstructed shell commands.
- [ ] MUST NOT discover executables, arguments, source files, or runtime configuration automatically.
- [ ] MUST NOT insert timestamps, random values, hostnames, paths, or environment values into descriptors.
- [ ] MUST NOT accept unvalidated descriptor content as authoritative benchmark identity.
- [ ] Individual benchmark suites MUST own descriptor schemas and strict validation before fingerprint calculation.
- [ ] Changes to validated benchmark semantics MUST invalidate the corresponding fingerprint.

##### `ProtocolIdentity`

- [ ] MUST provide an immutable value object representing:
  - [ ] `protocol_id: string`
  - [ ] `protocol_version: int`
- [ ] `protocol_id` MUST match `\A[a-z][a-z0-9-]*\z`.
- [ ] `protocol_id` MUST NOT exceed 64 bytes.
- [ ] `protocol_version` MUST be a positive integer within `1..65535`.
- [ ] Invalid identifiers and versions MUST be rejected.
- [ ] Equality MUST require exact protocol id and version equality.
- [ ] Protocol identity MUST NOT be derived from runner name, repository branch, timestamps, or environment variables.
- [ ] The toolkit MUST NOT define one global protocol version shared by all benchmark suites.
- [ ] Individual suites MUST own their measurement protocol identifiers and versions.
- [ ] A suite MUST reject baseline comparisons across incompatible measurement protocol versions.
- [ ] Changes to measurement semantics MUST require explicit protocol-version review.
- [ ] Protocol-version changes MUST require deliberate baseline regeneration before authoritative comparison.
- [ ] Existing suite-specific baseline layouts MUST NOT be silently rewritten to include a different protocol representation.

##### `CanonicalBaselineJson`

- [ ] MUST provide deterministic in-memory JSON baseline encoding and decoding.
- [ ] MUST reuse `Coretsia\Devtools\InternalToolkit\Json::encodeStable()` for encoding.
- [ ] MUST NOT introduce another stable JSON encoder.
- [ ] MUST NOT introduce a recursive JSON normalization framework.
- [ ] Canonical encoding MUST produce:

  `Json::encodeStable($validatedPayload) . "\n"`

- [ ] Canonical JSON MUST use:
  - [ ] UTF-8.
  - [ ] deterministic map-key ordering.
  - [ ] caller-preserved list ordering.
  - [ ] LF-only line endings.
  - [ ] exactly one final LF.
- [ ] Decoding MUST use native `json_decode()` with:
  - [ ] associative-array mode.
  - [ ] explicit maximum depth `8`.
  - [ ] `JSON_THROW_ON_ERROR`.
- [ ] Decoded root payload MUST be a non-empty associative map.
- [ ] Empty, invalid, malformed, or non-object JSON roots MUST be rejected.
- [ ] Non-canonical JSON representations MUST be rejected.
- [ ] Canonical re-encoding MUST reproduce the original bytes exactly.
- [ ] Duplicate JSON object keys MUST be rejected through byte-exact canonical re-encoding.
- [ ] Floats and unsupported JSON values MUST NOT be accepted as canonical baseline values.
- [ ] Baseline size limits MUST be explicitly supplied by the consuming suite.
- [ ] The foundation MUST validate requested size limits within `1..1048576` bytes.
- [ ] The actual payload byte length MUST be checked against the configured limit.
- [ ] Encoding and decoding MUST operate only on in-memory bytes and validated arrays.
- [ ] Baseline file opening, symlink checks, atomic writes, and repository path validation MUST remain responsibilities of existing tooling filesystem boundaries.
- [ ] The toolkit MUST NOT own a global baseline filesystem location.
- [ ] The toolkit MUST NOT define one universal baseline schema for all benchmark suites.
- [ ] Individual suites MUST validate their exact baseline schemas, benchmark identifiers, metric units, bounds, fingerprints, and protocol identity before accepting baseline data.
- [ ] Canonical encoding alone MUST NOT be considered proof that a baseline is semantically valid.
- [ ] Baseline publication MUST remain an explicit operation owned by the consuming suite.
- [ ] The foundation MUST NOT perform implicit baseline generation or updates.

#### Measurement protocol ownership (MUST)

- [ ] Every future benchmark suite MUST explicitly define its measurement protocol.
- [ ] Protocol-specific configuration MAY include:
  - [ ] benchmark execution target.
  - [ ] warmup count.
  - [ ] measured sample count.
  - [ ] sample aggregation method.
  - [ ] measurement units.
  - [ ] timing boundaries.
  - [ ] process or runtime isolation policy.
  - [ ] environment fingerprint policy.
  - [ ] timeout and cleanup semantics.
  - [ ] regression threshold.
  - [ ] baseline schema and location.
- [ ] This epic MUST NOT prescribe identical values for different suites.
- [ ] This epic MUST NOT introduce hidden process execution policies.
- [ ] This epic MUST NOT select default warmup counts or process timeouts.
- [ ] This epic MUST NOT introduce an implicit baseline-update policy.
- [ ] Each suite MUST own its benchmark case ordering and execution semantics.
- [ ] Each suite MUST own its regression findings and failure-code selection.
- [ ] Shared foundation changes MUST NOT silently change existing benchmark protocol behavior.
- [ ] Future adoption MUST preserve existing observable benchmark semantics unless an explicit protocol revision is approved.

#### Configuration (keys + defaults)

- Files:
  - N/A — no runtime or repository benchmark configuration is owned by this foundation epic.

- Keys:
  - N/A — no application configuration roots or default benchmark profiles are introduced.

- Rules:
  - [ ] No global mutable benchmark configuration.
  - [ ] No environment-derived defaults.
  - [ ] No hardcoded repository-specific baseline paths.
  - [ ] No implicit selection of benchmark runner classes.
  - [ ] No implicit measurement protocol version.
  - [ ] No runtime override of committed benchmark policy.
  - [ ] Validation of values MUST occur before statistical processing, fingerprint calculation, or baseline serialization.
  - [ ] Suite-specific configuration validation remains the consuming suite's responsibility.

#### Wiring / DI tags (when applicable)

N/A.

- [ ] No reserved tags are introduced.
- [ ] No ServiceProvider is introduced.
- [ ] No application DI registration is performed.
- [ ] No runtime module or feature-pack discovery is introduced.
- [ ] No existing tag ownership or metadata schema is modified.

#### Artifacts / outputs (if applicable)

- Canonical production artifacts:
  - N/A.

- Committed benchmark baselines:
  - N/A — owned by future benchmark suites.

- Benchmark reports:
  - N/A — owned by future benchmark suites.

- In-memory outputs:
  - [ ] validated measurement statistics.
  - [ ] duration comparison results.
  - [ ] protocol identity.
  - [ ] runtime identity.
  - [ ] scenario fingerprints.
  - [ ] canonical baseline JSON bytes.

- Rules:
  - [ ] The foundation MUST NOT write files.
  - [ ] The foundation MUST NOT generate production artifacts.
  - [ ] The foundation MUST NOT create application cache files.
  - [ ] The foundation MUST NOT publish benchmark reports.
  - [ ] The foundation MUST NOT persist measurement history.
  - [ ] The foundation MUST NOT execute external processes.
  - [ ] Synthetic test outputs MUST remain test-owned and MUST NOT become authoritative performance evidence.

---

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A.

- [ ] The package MUST NOT access application ContextStore.
- [ ] The package MUST NOT read or write runtime ContextKeys.
- [ ] The package MUST NOT participate in UnitOfWork lifecycle execution.
- [ ] The package MUST NOT introduce stateful application services.
- [ ] No `kernel.reset` integration is required.
- [ ] Shared calculation and serialization primitives MUST remain stateless.
- [ ] Protocol and identity value objects MUST be immutable.

#### Observability (policy-compliant)

- Spans:
  - N/A.

- Metrics:
  - N/A — benchmark measurements MUST NOT be registered as production observability metrics.

- Logs:
  - N/A — this package MUST NOT introduce independent logging infrastructure.

- Tooling outputs:
  - [ ] Return validated values or safe failures only.
  - [ ] MUST NOT print benchmark results directly.
  - [ ] MUST NOT emit diagnostic lines independently of consuming tooling entrypoints.
  - [ ] MUST NOT introduce a competing console output service.
  - [ ] MUST NOT introduce production tracing, logging, or metrics dependencies.
  - [ ] Actual benchmark reports remain responsibilities of future benchmark suites.

#### Errors

- Exceptions introduced:
  - [ ] Invalid measurement, comparison, identity, protocol, and baseline inputs MUST produce deterministic tooling failures.
  - [ ] Validation failures MUST use bounded stable failure messages.
  - [ ] The package MUST use `RuntimeException` for its own supported validation failures.
  - [ ] The package MUST NOT introduce `InvalidArgumentException` for its own new validation boundaries.
  - [ ] Unexpected native failures MUST NOT be rendered directly to benchmark diagnostics.
  - [ ] Failure messages MUST NOT contain rejected input values.

- Mapping:
  - [ ] No runtime error mapper is introduced.
  - [ ] No `error.mapper` tag is introduced.
  - [ ] No new global gate output boundary is introduced.
  - [ ] Future benchmark gates MUST map toolkit failures through the existing `GateRuntime` boundary.
  - [ ] Future gate-specific error codes MUST be owned and registered by their respective benchmark gate epics.
  - [ ] This epic MUST NOT register unused performance gate error codes.

#### Security / Redaction

- [ ] The package MUST NOT depend on production `SensitiveDataRedactorInterface` or `platform/redaction`.
- [ ] The package MUST NOT access application authentication state.
- [ ] The package MUST NOT inspect cookies, sessions, request headers, or request bodies.
- [ ] The package MUST NOT read secrets or credentials.
- [ ] The package MUST NOT access arbitrary application payloads.
- [ ] The package MUST NOT execute shell commands.
- [ ] The package MUST NOT perform network requests.
- [ ] The package MUST NOT evaluate arbitrary PHP code.
- [ ] The package MUST NOT deserialize PHP objects from benchmark input.
- [ ] Runtime identity MUST contain only the documented observable fields.
- [ ] Scenario fingerprinting MUST operate only on caller-validated safe descriptors.
- [ ] Baseline serialization MUST NOT be treated as an automatic redaction mechanism.
- [ ] Consumers MUST exclude secrets and sensitive data through strict schema validation before invoking canonical serialization.

- Forbidden failure and diagnostic data:
  - [ ] credentials.
  - [ ] authentication tokens.
  - [ ] cookies or session identifiers.
  - [ ] raw SQL queries.
  - [ ] raw HTTP payloads.
  - [ ] raw process output.
  - [ ] raw command arguments in diagnostic messages.
  - [ ] environment values.
  - [ ] absolute filesystem paths.
  - [ ] stack traces.
  - [ ] untrusted exception messages.

- Allowed values:
  - [ ] validated canonical scenario identifiers.
  - [ ] validated protocol identifiers.
  - [ ] validated measurement units.
  - [ ] bounded positive integer measurements.
  - [ ] validated threshold percentages.
  - [ ] canonical SHA-256 fingerprints.
  - [ ] explicitly allowed runtime identity fields.

---

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- Context writes:
  - N/A — no runtime context access.

- Runtime reset:
  - N/A — no runtime services or stateful runtime lifecycle.

- Runtime observability:
  - N/A — no runtime spans, metrics, or logs.

- Security / Redaction:
  - [ ] `tests/Contract/FailureOutputSafetyTest.php` MUST verify that malformed inputs and failures do not expose rejected values, secrets, paths, or environment-like data.

- Tooling isolation:
  - [ ] `tests/Contract/ToolingIsolationTest.php` MUST verify that the package does not introduce forbidden runtime dependencies or runtime registration.

#### Deterministic statistics verification

- [ ] Identical measurement lists MUST produce identical medians.
- [ ] Reordering identical measurement values MUST NOT change the median.
- [ ] Invalid measurement counts MUST fail deterministically.
- [ ] Invalid measurement types MUST fail deterministically.
- [ ] Median computation MUST NOT depend on wall-clock time.
- [ ] Regression equality at the configured threshold MUST pass.
- [ ] Regression strictly above the configured threshold MUST be detected.
- [ ] Arithmetic overflow MUST be rejected before multiplication.
- [ ] Statistical calculations MUST NOT depend on floating-point rounding.

#### Runtime identity verification

- [ ] Runtime identity fields MUST have the documented exact types.
- [ ] Runtime identity construction MUST derive observable fields from the executing PHP environment.
- [ ] Canonical configuration hashing MUST be independent of extension enumeration order.
- [ ] Identical validated configurations MUST produce identical fingerprints.
- [ ] Changes to fingerprinted PHP configuration MUST change the fingerprint.
- [ ] Runtime identity MUST NOT contain paths, hostnames, timestamps, or environment values.
- [ ] Invalid identity shapes MUST be rejected.
- [ ] Tests MUST NOT claim that matching PHP identity proves matching physical hardware.

#### Scenario fingerprint verification

- [ ] Equivalent canonical descriptors MUST produce identical fingerprints.
- [ ] Map-key ordering differences MUST NOT affect the fingerprint.
- [ ] Ordered list changes MUST affect the fingerprint.
- [ ] Validated scenario content changes MUST affect the fingerprint.
- [ ] Unsupported values MUST be rejected.
- [ ] Fingerprints MUST be lowercase SHA-256 hexadecimal strings.
- [ ] Fingerprinting MUST NOT execute benchmark targets.

#### Baseline serialization verification

- [ ] Canonical encoding MUST be byte-stable.
- [ ] Canonical output MUST contain exactly one final LF.
- [ ] Canonical output MUST use LF-only line endings.
- [ ] Canonical map-key ordering MUST be stable.
- [ ] Canonical list ordering MUST be preserved.
- [ ] Valid canonical JSON MUST round-trip without byte differences.
- [ ] Non-canonical whitespace MUST be rejected.
- [ ] Duplicate object keys MUST be rejected.
- [ ] Malformed JSON MUST be rejected.
- [ ] Float values MUST be rejected.
- [ ] Missing or extra final newline bytes MUST be rejected.
- [ ] Empty or invalid root payloads MUST be rejected.
- [ ] Oversized payloads MUST be rejected.
- [ ] Decoding MUST NOT automatically convert schema-invalid values into valid baseline evidence.
- [ ] No test MUST write to a production committed baseline.

#### Synthetic integration verification

- [ ] One isolated synthetic integration test MUST combine:
  - [ ] protocol identity creation.
  - [ ] canonical scenario fingerprinting.
  - [ ] deterministic synthetic measurements.
  - [ ] median calculation.
  - [ ] duration regression comparison.
  - [ ] runtime identity representation.
  - [ ] canonical baseline encoding.
  - [ ] canonical baseline decoding.
  - [ ] strict equality of expected protocol and scenario identity.
- [ ] The fixture MUST use fixed synthetic integer measurements.
- [ ] The fixture MUST NOT execute a real CLI, HTTP, database, or filesystem benchmark.
- [ ] The fixture MUST NOT rely on elapsed wall-clock thresholds.
- [ ] The fixture MUST NOT require Linux or a dedicated benchmark runner.
- [ ] The fixture MUST NOT modify repository source files or committed baseline files.
- [ ] Synthetic measurements MUST NOT be represented as authoritative performance evidence.

#### Test harness / fixtures (when integration is needed)

- Fixture app:
  - N/A — no application boot is required.

- Synthetic fixtures:
  - [ ] Synthetic benchmark descriptors and measurement arrays MUST be defined locally in package tests.
  - [ ] Synthetic fixtures MUST NOT require production runtime services.
  - [ ] Fixtures MUST NOT introduce alternative implementations of canonical tooling JSON serialization.
  - [ ] Fixtures MUST NOT simulate hardware identity as authoritative evidence.

- Fake adapters:
  - N/A — no process, network, filesystem, observability, or service-container adapters are introduced.

---

### Tests (MUST)

#### Unit

- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Statistics/IntegerMedianTest.php`
  - [ ] exact median for three, five, and nine samples.
  - [ ] ordering independence.
  - [ ] malformed sample rejection.
  - [ ] unsupported count rejection.
  - [ ] integer-only behavior.

- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Comparison/DurationRegressionTest.php`
  - [ ] exact threshold comparison.
  - [ ] equality is accepted.
  - [ ] strictly greater degradation is rejected.
  - [ ] invalid duration rejection.
  - [ ] invalid threshold rejection.
  - [ ] overflow rejection.
  - [ ] no floating-point arithmetic.

- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Identity/PhpRuntimeIdentityTest.php`
  - [ ] canonical field set.
  - [ ] PHP configuration fingerprint stability.
  - [ ] extension ordering independence.
  - [ ] identity mismatch detection.
  - [ ] unsupported configuration rejection.
  - [ ] no sensitive runtime values in identity.

- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Fingerprint/ScenarioFingerprintTest.php`
  - [ ] canonical fingerprint stability.
  - [ ] ordered-list sensitivity.
  - [ ] map-order independence.
  - [ ] unsupported input rejection.

- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Protocol/ProtocolIdentityTest.php`
  - [ ] valid protocol identifiers.
  - [ ] invalid identifier rejection.
  - [ ] version bounds.
  - [ ] exact protocol equality.
  - [ ] protocol mismatch rejection.

- [ ] `packages/devtools/benchmark-toolkit/tests/Unit/Baseline/CanonicalBaselineJsonTest.php`
  - [ ] canonical encoding.
  - [ ] canonical decoding.
  - [ ] round-trip byte equality.
  - [ ] duplicate-key rejection.
  - [ ] malformed JSON rejection.
  - [ ] size bounds.
  - [ ] newline policy.
  - [ ] unsupported value rejection.

#### Contract

- [ ] `packages/devtools/benchmark-toolkit/tests/Contract/ToolingIsolationTest.php`
  - [ ] no production package dependencies.
  - [ ] no runtime module registration.
  - [ ] no DI service-provider wiring.
  - [ ] no repository `tools/support/*` dependency from package source.
  - [ ] no benchmark execution side effects.

- [ ] `packages/devtools/benchmark-toolkit/tests/Contract/FailureOutputSafetyTest.php`
  - [ ] no rejected input values in validation failures.
  - [ ] no secret-like values in failures.
  - [ ] no absolute paths.
  - [ ] no environment values.
  - [ ] no raw payloads.
  - [ ] no stack traces printed by the package.

#### Integration

- [ ] `packages/devtools/benchmark-toolkit/tests/Integration/BenchmarkToolkitIntegrationTest.php`
  - [ ] synthetic end-to-end composition of toolkit primitives.
  - [ ] deterministic baseline bytes.
  - [ ] correct protocol comparison.
  - [ ] correct scenario fingerprints.
  - [ ] correct integer median and threshold results.
  - [ ] malformed input fails closed.
  - [ ] no production benchmark execution.
  - [ ] no filesystem or repository mutations.

#### Gates / Arch

- [ ] Existing package compliance gates MUST pass.
- [ ] Existing package publish-safety gates MUST pass.
- [ ] Existing internal-toolkit duplication gate MUST pass.
- [ ] Existing tooling invalid-argument policy MUST pass where applicable.
- [ ] Existing repository text-normalization and license-header gates MUST pass.
- [ ] Existing Deptrac rules MUST enforce the declared dependency direction.
- [ ] Existing root Composer validation MUST pass.
- [ ] Generated package index MUST remain synchronized.
- [ ] Generated Deptrac configuration MUST remain synchronized.
- [ ] No new independent benchmark foundation gate MUST be introduced solely to duplicate existing architecture enforcement.
- [ ] Generic CI MUST NOT perform real wall-clock regression comparisons.

---

### DoD (MUST)

#### Package completion

- [ ] All declared package files exist at their canonical paths.
- [ ] Package manifest is valid and conforms to Coretsia package conventions.
- [ ] Package is registered in the root Composer workspace.
- [ ] Root `composer.lock` is updated and committed.
- [ ] Package uses the repository-wide release-line policy.
- [ ] Package is installed only as a development dependency.
- [ ] Package is discoverable through the existing monorepo autoloader.
- [ ] No production package depends on the benchmark toolkit.
- [ ] No module, ServiceProvider, runtime tag, or application config root is introduced.

#### Functional guarantees

- [ ] Integer median implementation is complete and tested.
- [ ] Integer-only duration regression comparison is complete and tested.
- [ ] Observable PHP runtime identity is complete and tested.
- [ ] Canonical PHP configuration fingerprinting is complete and tested.
- [ ] Scenario fingerprinting is complete and tested.
- [ ] Protocol identity validation is complete and tested.
- [ ] Canonical baseline JSON encoding and decoding are complete and tested.
- [ ] All public foundation primitives have explicit validated input and output contracts.
- [ ] No independent JSON encoder, filesystem abstraction, process runner, or gate execution framework is introduced.
- [ ] No benchmark-specific baseline schema is imposed on all future suites.

#### Determinism

- [ ] Equivalent validated inputs produce equivalent results.
- [ ] Canonical JSON bytes remain stable across repeated execution.
- [ ] Fingerprint generation is deterministic.
- [ ] Statistical calculations use integer-only arithmetic.
- [ ] No random data, timestamps, mutable environment values, or filesystem ordering affect pure calculations.
- [ ] Generated package metadata and architecture files pass rerun-no-diff checks.

#### Verification

- [ ] All declared unit tests pass.
- [ ] All declared contract tests pass.
- [ ] Synthetic integration tests pass.
- [ ] No test requires a dedicated benchmark runner.
- [ ] No test asserts real elapsed-time performance thresholds.
- [ ] No committed production benchmark baseline is created or modified.
- [ ] Existing architecture and package compliance gates pass.
- [ ] Dependency graph remains acyclic.
- [ ] Security and diagnostic safety requirements are verified.

#### Documentation

- [ ] Package `README.md` is complete.
- [ ] `docs/benchmarks/methodology.md` is complete.
- [ ] Package ownership and dependency boundaries are documented.
- [ ] Initial foundation API is documented.
- [ ] Measurement protocol ownership is documented.
- [ ] Baseline validation and persistence ownership are documented.
- [ ] Runtime identity limitations are documented.
- [ ] Future suite extension policy is documented.
- [ ] `ssot_refs` and ADR requirements are reviewed; no unnecessary SSoT or ADR is introduced.

#### Performance Roadmap independence

- [ ] No product phase declares `PERF.10.0` as a release-blocking prerequisite.
- [ ] No production package requires `coretsia/devtools-benchmark-toolkit`.
- [ ] No product release mode requires a benchmark baseline from this epic.
- [ ] No dedicated GitHub self-hosted runner is required.
- [ ] No authoritative benchmark measurements are required.
- [ ] No performance regression gate is activated.
- [ ] Future performance suites MAY depend on this foundation after it is completed.
- [ ] The implementation of subsequent performance epics remains optional and independently scheduled.

---

### Deferred Scope / Future Extensions (NON-BLOCKING)

The following capabilities are explicitly outside `PERF.10.0` and MUST NOT be required for its completion.

- [ ] CLI process benchmarking and process lifecycle management.
- [ ] HTTP request benchmarking and load generation.
- [ ] Container, routing, and Kernel microbenchmark execution.
- [ ] Database and filesystem benchmark execution.
- [ ] Asynchronous events, queue, worker, and scheduler benchmarking.
- [ ] Generic benchmark adapter registries.
- [ ] Benchmark execution plugin discovery.
- [ ] Universal benchmark configuration DSL.
- [ ] Concurrent or distributed benchmark scheduling.
- [ ] Hardware provisioning and CPU affinity management.
- [ ] GitHub self-hosted runner provisioning.
- [ ] Authoritative baseline generation and publication.
- [ ] Historical performance result storage.
- [ ] Performance dashboards and trend visualization.
- [ ] Percentile distributions and advanced statistical analysis.
- [ ] Confidence intervals and statistical significance testing.
- [ ] Higher-is-better throughput regression policies.
- [ ] Memory allocation and peak-memory regression policies.
- [ ] Cross-runtime or cross-hardware performance comparisons.
- [ ] External benchmark service integration.
- [ ] Production profiling and observability integration.
- [ ] Automatic performance optimization.

---

### PERF.20.0 CLI Performance Gate (MUST) [TOOLING]

---
type: tools
phase: PERF
track: performance
epic_id: "PERF.20.0"
owner_path: "tools/gates/"

execution_policy: deferred
required_for_product_release: false
activation: explicit
eligible_after:
- "1.50.0"
- "2.30.0"

goal: "Detect end-to-end CLI performance regressions on a dedicated pinned benchmark runner using a fixed measurement protocol, a committed runner-bound baseline, and bounded process execution."
provides:
- "Repeatable end-to-end CLI process benchmarking on a pinned runner"
- "Canonical committed tooling baseline with explicit runner identity and benchmark cases"
- "Dedicated CI performance gate isolated from generic shared-runner CI"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/sensitive-data-redaction.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.50.0 — tooling baseline exists
  - 2.30.0 — tag-first Platform CLI exists
  - PERF.10.0 — shared benchmark toolkit foundation exists

- Required deliverables:
  - `coretsia` CLI executable.
  - Repository-root `composer.lock` — committed and consistent with the root `composer.json`.
  - If the root lockfile is absent, it MUST be generated, reviewed, and committed before enabling the dedicated performance benchmark workflow.
  - The performance gate MUST NOT introduce an independent dependency resolver, alternative Composer lockfile, or tooling-specific dependency installation layer.

- Benchmark execution prerequisites:
  - The repository-root `coretsia` executable remains the canonical benchmark target.
  - Benchmark commands MUST execute against the monorepo application root, not the Skeleton package source directory.
  - The dedicated benchmark runner MUST be provisioned before enabling CI performance gating.
  - The pinned benchmark runner MUST be a dedicated, explicitly provisioned runner with stable hardware and software configuration.
  - The CI workflow owns the trusted runner selection and CPU/runner-class guarantee.
  - The gate MUST independently validate the observable runtime identity against the committed baseline, including OS family, architecture, PHP version, and the explicitly selected PHP runtime configuration.
  - The gate MUST NOT treat a configurable runner-name string or an arbitrary environment variable as sufficient proof of pinned hardware identity.
  - A baseline with a different observable runtime identity MUST NOT be accepted.
  - Hardware or toolchain changes require deliberate baseline regeneration and review.
  - Generic CI MUST NOT execute the real performance gate or enforce committed wall-clock baselines.
  - Developer machines MAY execute synthetic benchmark integration tests.
  - Direct real-gate execution outside the dedicated pinned runner is unsupported and its measurements MUST NOT be considered authoritative.
  - Matching observable runtime identity does not independently prove hardware or runner-class identity; trusted CI runner selection owns that guarantee.

#### Compile-time deps

- Tooling:
  - `tools/support` — existing repository gate infrastructure
  - `coretsia/devtools-internal-toolkit` — tooling-only stable JSON encoding
- Benchmark target:
  - existing repository-root `coretsia` executable
- Forbidden:
  - direct imports or calls to `core/foundation`, `core/kernel`, or `platform/*` implementations from performance gate infrastructure
  - new production package dependencies introduced solely for performance benchmarking
  - independent JSON encoding, diagnostic output, or gate execution frameworks

The benchmarked CLI process MAY execute production runtime code. This does not make production implementations dependencies of the gate itself.

### Entry points / integration points (MUST)

- Composer:
  - `composer performance:gate` — runs performance benchmarks against the committed pinned-runner baseline
  - `composer performance:gate -- --update-baseline` — explicitly regenerates the baseline on the pinned runner
  - `performance:gate` MUST NOT be included in `composer gates` or `composer ci`
- CI:
  - run the performance gate only in a dedicated pinned benchmark job
  - MUST NOT gate generic shared-runner CI
  - synthetic integration tests MAY run in generic CI without enforcing real performance thresholds
  - the benchmark workflow MUST NOT execute untrusted pull-request code on a privileged self-hosted runner

### Deliverables (MUST)

#### Creates

- [ ] `tools/config/performance.php` — tooling-local performance benchmark config:
  - [ ] the configuration file MUST return one non-empty PHP array
  - [ ] the top-level configuration MUST contain exactly:
    - [ ] `runner_class: string`
    - [ ] `timeout_seconds: int`
    - [ ] `threshold_percent: int`
    - [ ] `cases: list<array>`
  - [ ] `runner_class` MUST match `\A[a-z][a-z0-9-]*\z` and MUST NOT exceed 64 bytes
  - [ ] `runner_class` identifies the documented CI runner class but MUST NOT be treated as independent proof of hardware identity
  - [ ] `timeout_seconds` MUST satisfy the existing `1..30` bounds
  - [ ] `threshold_percent` MUST be exactly integer `120` for the initial measurement protocol
  - [ ] `cases` MUST be a non-empty ordered list with the existing maximum of eight entries
  - [ ] unknown top-level keys, missing keys, invalid types, and non-list case collections MUST be rejected
  - [ ] configuration validation MUST use the existing GateRuntime tooling-failure boundary
  - [ ] fixed ordered benchmark cases with unique canonical safe ASCII ids
  - [ ] benchmark ids match `\A[a-z][a-z0-9-]*\z` and are at most 64 bytes
  - [ ] at least one and at most eight benchmark cases are permitted
  - [ ] every case contains exactly `id` and `arguments`
  - [ ] `arguments` MUST be a non-empty ordered list of strings
  - [ ] each case MUST contain at most 16 argument tokens
  - [ ] each argument token MUST contain 1..256 bytes
  - [ ] total argument-token bytes per case MUST NOT exceed 4096
  - [ ] argument tokens MUST contain only printable ASCII bytes
  - [ ] NUL, CR, LF, control bytes, and malformed argument values are forbidden
  - [ ] benchmark configuration MUST NOT contain credentials, secrets, or environment-derived argument values
  - [ ] benchmark case validation MUST complete before fingerprint calculation or process execution
  - [ ] each benchmark case is bound to the canonical fingerprint of its fixed ordered argument-token list
  - [ ] the fingerprint MUST be calculated as `hash('sha256', Json::encodeStable($arguments))`
  - [ ] `Json` MUST refer to `Coretsia\Devtools\InternalToolkit\Json`
  - [ ] the fingerprint input MUST be the validated ordered argument-token list
  - [ ] MUST NOT use production Foundation serialization implementations
  - [ ] benchmark fingerprints MUST NOT be derived from shell-command strings
  - [ ] each case declares one fixed ordered list of CLI argument tokens
  - [ ] benchmark commands execute only the canonical repo-root `coretsia` entrypoint through `PHP_BINARY`
  - [ ] benchmark commands MUST NOT be constructed from shell strings, environment values, or runtime-discovered executables
  - [ ] committed production benchmark cases MUST be non-destructive and MUST NOT depend on mutable generated artifacts
  - [ ] production benchmark cases MUST NOT modify application configuration, runtime artifacts, or repository source files
  - [ ] isolated synthetic integration fixtures MAY write temporary execution markers within their owned test directories to verify execution count, ordering, and cleanup
  - [ ] synthetic fixture side effects MUST remain confined to test-owned temporary directories and MUST be removed during test cleanup
  - [ ] synthetic execution markers MUST NOT participate in production benchmark measurements or baseline fingerprints
  - [ ] use explicit CLI-global output options where needed to preserve comparable output behavior
  - [ ] fixed warmup count: `1` per benchmark case
  - [ ] fixed measured execution count: `5` per benchmark case
  - [ ] default per-execution timeout: `30` seconds
  - [ ] timeout MUST be a validated configuration integer in `1..30` seconds
  - [ ] the production benchmark configuration MUST use `30` seconds
  - [ ] isolated integration fixtures MAY use a shorter fixed timeout through their own copied configuration
  - [ ] timeout configuration MUST NOT introduce runtime CLI overrides or test-only production arguments
  - [ ] threshold percentage: `120` (20% regression allowance)
  - [ ] benchmark durations and baseline values use positive integer microseconds
  - [ ] baseline file path `tools/config/performance.baseline.json`
  - [ ] expected pinned runner identity is explicit and validated
  - [ ] the measurement protocol has one fixed version recorded in the baseline
  - [ ] the child environment allowlist is fixed and explicitly documented for the pinned runner
  - [ ] no arbitrary environment passthrough or environment-derived benchmark arguments are permitted
  - [ ] changes to benchmark argument tokens MUST invalidate the matching baseline benchmark fingerprint
  - [ ] changes to the benchmark executable, working-directory policy, child environment policy, PHP configuration fingerprint policy, warmup count, measured execution count, polling interval, timeout semantics, or process-output handling MUST require a measurement protocol version increment
  - [ ] a measurement protocol version mismatch MUST invalidate the complete committed baseline
  - [ ] changing only the degradation threshold does not alter measured durations but MUST require explicit review of the performance-gating policy
  - [ ] the gate MUST NOT compare measurements produced under different measurement protocol versions
  - [ ] measurement protocol changes MUST require explicit baseline regeneration and review on the pinned runner
  - [ ] all configuration keys, types, bounds, benchmark ids, and token lists are strictly validated before executing any benchmark

- [ ] `tools/support/PerformanceBenchmarkMath.php` — tooling-local pure benchmark arithmetic:
  - [ ] final stateless class with no filesystem, process, environment, Kernel, or runtime dependencies
  - [ ] validates and calculates the median of exactly five positive integer microsecond measurements
  - [ ] performs the canonical bounded integer-only degradation comparison
  - [ ] rejects invalid, overflowing, or unsupported numeric inputs using fixed safe `RuntimeException` messages
  - [ ] MUST NOT throw `InvalidArgumentException`, as forbidden by the existing `tools_invalid_argument_exception_gate.php` policy
  - [ ] rejected values, measurements, and calculation operands MUST NOT appear in exception messages
  - [ ] requires 64-bit PHP integer arithmetic
  - [ ] supports positive durations up to `60000000` microseconds
  - [ ] validates integer bounds before multiplication
  - [ ] never converts benchmark durations or threshold comparisons to floating-point values
  - [ ] invalid arithmetic inputs MUST produce deterministic tooling failures without exposing rejected values
  - [ ] produces no diagnostics, files, logs, or output
  - [ ] MUST NOT introduce a generic timing service, process runner, or second gate execution boundary

- [ ] `tools/gates/performance_gate.php` — deterministic gate:
  - [ ] validates the complete benchmark configuration before executing any benchmark
  - [ ] normal gate execution MUST load and validate the committed baseline before executing any benchmark
  - [ ] explicit `--update-baseline` execution MUST allow the initial baseline to be absent
  - [ ] baseline-update execution MUST validate the complete benchmark configuration and current runtime identity before measuring
  - [ ] normal execution MUST reject missing, duplicate, invalid, or unexpected baseline benchmark ids
  - [ ] normal execution MUST reject benchmark case fingerprint or measurement protocol mismatches
  - [ ] normal execution MUST validate the current runtime identity against the baseline runtime identity
  - [ ] baseline-update execution MUST regenerate all configured benchmark cases rather than partially updating existing entries
  - [ ] executes one untimed warmup followed by exactly five measured executions per benchmark case
  - [ ] measures complete child-process execution using monotonic `hrtime(true)`
  - [ ] converts elapsed durations to positive integer microseconds
  - [ ] uses the median of the five measured executions as the current benchmark value
  - [ ] MUST reuse `PerformanceBenchmarkMath` for median calculation and threshold comparison
  - [ ] executes benchmark cases in their declared order
  - [ ] preserves the fixed benchmark case identity and argument-token ordering
  - [ ] diagnostics identify a benchmark only by its canonical safe benchmark id
  - [ ] diagnostics MUST NOT reconstruct, join, quote, or print the raw command line
  - [ ] benchmark children execute through `proc_open()` with argument vectors and no shell interpolation
  - [ ] child stdout and stderr MUST be redirected to the platform null sink without creating output pipes
  - [ ] child stdin MUST use the platform null sink
  - [ ] child output MUST NOT be captured, buffered, inherited, or copied into diagnostics
  - [ ] the platform null sink is `NUL` on Windows and `/dev/null` on other supported operating systems
  - [ ] benchmark child execution uses the canonical repository root as its working directory
  - [ ] child environment is constructed from an explicit bounded allowlist required for the pinned runtime
  - [ ] arbitrary inherited environment values MUST NOT be forwarded to benchmark children
  - [ ] child environment values MUST NOT be rendered or copied into diagnostics
  - [ ] each child execution has one fixed monotonic timeout deadline based on `hrtime(true)`
  - [ ] measurement starts immediately before `proc_open()` and ends when child termination is first observed
  - [ ] child process status MUST be polled using `proc_get_status()` with a requested `1000`-microsecond sleep between active-status checks
  - [ ] polling MUST use one monotonic deadline and MUST NOT extend the execution timeout
  - [ ] actual wake-up intervals MAY vary because of operating-system scheduling
  - [ ] elapsed measurements MUST use `hrtime(true)` rather than assuming an exact polling interval
  - [ ] the measurement protocol MUST account for polling-related timing variability when assessing benchmark reliability
  - [ ] child exit status MUST be captured and validated as an integer equal to `0`
  - [ ] the last valid exit status returned by `proc_get_status()` MUST be preserved before `proc_close()`
  - [ ] a naturally exited child MUST NOT be terminated again
  - [ ] immediately after successful `proc_open()`, the process handle MUST enter one `try/finally` cleanup boundary
  - [ ] every path after successful process startup MUST execute the same cleanup boundary
  - [ ] cleanup MUST execute after success, non-zero exit, timeout, status polling failure, or unexpected Throwable
  - [ ] timed-out or still-running children MUST receive a graceful termination request
  - [ ] graceful termination MUST have a fixed maximum deadline of `5` seconds
  - [ ] a child remaining active after graceful termination MUST receive a hard termination request where supported
  - [ ] hard termination MUST have one additional fixed maximum deadline of `5` seconds
  - [ ] termination deadlines MUST use monotonic `hrtime(true)` and MUST NOT be reset by polling
  - [ ] `proc_close()` MUST be called only after the child is confirmed inactive
  - [ ] a child remaining active after bounded termination attempts MUST produce the tooling-failure result
  - [ ] PHP process exit-status handling MUST account for the cached `proc_get_status()` exit code and MUST NOT treat `proc_close()` returning `-1` alone as proof of command failure
  - [ ] cleanup failures MUST override previously selected successful or performance-regression results
  - [ ] cleanup MUST NOT emit diagnostics independently of the existing GateRuntime boundary
  - [ ] on Windows, `proc_open()` MUST use an argument vector with `bypass_shell = true`
  - [ ] expected process-operation warnings MUST be suppressed through the existing `GateRuntime::withSuppressedErrors()` boundary
  - [ ] process startup, timeout, invalid exit status, termination failure, or incomplete cleanup MUST produce the tooling-failure code
  - [ ] failed process execution MUST NOT produce a successful measurement or update the baseline
  - [ ] normal gate execution MUST NOT create, replace, or modify the baseline
  - [ ] missing, unreadable, malformed, or runner-incompatible baseline MUST fail with `CORETSIA_PERFORMANCE_GATE_SCAN_FAILED`
  - [ ] baseline case ids MUST exactly match the configured benchmark case ids
  - [ ] compares positive integer microsecond durations using integer-only percentage arithmetic
  - [ ] degradation is defined exactly as `current_us * 100 > baseline_us * threshold_percent`
  - [ ] equality with the configured threshold is not a degradation
  - [ ] duration values and threshold percentage MUST be bounded before multiplication to prevent integer overflow
  - [ ] malformed, zero, negative, overflowing, or out-of-range durations MUST fail through the tooling-failure boundary
  - [ ] all configured benchmark cases MUST complete successfully before the final performance verdict is selected
  - [ ] completed performance-regression findings MUST be accumulated using canonical safe benchmark ids
  - [ ] if all benchmark executions and cleanup operations succeed and at least one benchmark exceeds the threshold, emits `CORETSIA_PERFORMANCE_DEGRADED` with safe bounded diagnostics
  - [ ] after successful GateRuntime bootstrap, any benchmark execution, validation, or cleanup failure MUST produce `CORETSIA_PERFORMANCE_GATE_SCAN_FAILED`
  - [ ] failures occurring before successful GateRuntime bootstrap MUST retain the existing canonical tooling bootstrap error behavior
  - [ ] the performance gate MUST NOT intercept, replace, or duplicate errors emitted by `tools/support/bootstrap.php`
  - [ ] tooling failures MUST take precedence over accumulated performance-regression findings
  - [ ] partial measurements or completed regression findings MUST NOT be emitted after a tooling failure
  - [ ] a failed benchmark process MUST NOT be interpreted as a performance regression
  - [ ] uses `ConsoleOutput`
  - [ ] accepts exactly two invocation forms:
    - [ ] no arguments — normal performance gate execution
    - [ ] one `--update-baseline` flag — explicit baseline regeneration
  - [ ] duplicate flags, unknown options, positional arguments, and valued flag forms MUST be rejected
  - [ ] invalid invocation MUST fail through the existing GateRuntime tooling-failure boundary
  - [ ] argument validation MUST occur before configuration loading, baseline reading, or process execution
  - [ ] MUST NOT introduce a generic argument parser or duplicate the existing CLI parser
  - [ ] `--update-baseline` requires the same validated pinned runner identity and measurement protocol as normal gate execution
  - [ ] `--update-baseline` regenerates the complete baseline explicitly, including runner identity and all configured benchmark cases
  - [ ] all benchmark cases MUST complete their warmup, measured executions, validation, and process cleanup before baseline serialization begins
  - [ ] no partial baseline MUST be written after a benchmark execution or cleanup failure
  - [ ] baseline serialization MUST complete successfully before the atomic write is attempted
  - [ ] the existing baseline MUST remain unchanged when measurement or serialization fails before the write
  - [ ] baseline publication MUST use exactly one `DeterministicFile::writeBytesExact()` call for the complete canonical JSON payload
  - [ ] successful baseline publication MUST NOT emit performance-regression findings
  - [ ] normal gate execution and baseline-update execution are mutually exclusive
  - [ ] baseline updates MUST use the existing `DeterministicFile` atomic writing boundary
  - [ ] baseline updates MUST be reviewed and committed separately from automatic CI performance gating
  - [ ] no baseline is created implicitly by normal gate execution
  - [ ] MUST follow the existing repository gate script structure and naming conventions
  - [ ] MUST include the canonical PHP declaration, repository license header, and tooling imports used by existing gates
  - [ ] MUST load `tools/support/GateRuntime.php` through the existing relative `require_once` pattern
  - [ ] MUST invoke `GateRuntime::executeSelectedViolation()` exactly once as the top-level execution boundary
  - [ ] the gate callback MUST return the canonical `violationCode` and `diagnostics` outcome shape
  - [ ] MUST reuse `RepositoryContext` for repository-root and tools-root resolution
  - [ ] MUST reuse `GateRuntime` for bootstrap loading, fallback diagnostics, registered error-code validation, diagnostic deduplication, and deterministic sorting
  - [ ] MUST reuse `GateRuntime::withSuppressedErrors()` for supported warning-prone filesystem and process operations
  - [ ] MUST use `DeterministicFile` for baseline reading and atomic writing
  - [ ] MUST use `Coretsia\Devtools\InternalToolkit\Json::encodeStable()` for canonical baseline serialization and benchmark case fingerprints
  - [ ] MUST reuse the existing Composer autoload initialized by `tools/support/bootstrap.php`
  - [ ] MUST NOT load production Foundation serialization classes for tooling data
  - [ ] MUST NOT introduce another JSON encoder, recursive normalizer, or serialization adapter
  - [ ] MUST use registered `ErrorCodes` constants for violation and tooling-failure codes
  - [ ] MUST NOT introduce independent bootstrap, repository discovery, JSON encoding, diagnostic normalization, error-code validation, or exception rendering
  - [ ] unexpected configuration, benchmark, baseline, process, and cleanup failures MUST propagate to the existing GateRuntime failure boundary
  - [ ] MUST NOT print diagnostics directly or introduce another console writer
  - [ ] MUST NOT expose Throwable messages, stack traces, absolute paths, environment values, or child-process diagnostics
  - [ ] On pass:
    - [ ] MUST emit no output
    - [ ] MUST exit with code `0`
  - [ ] On violation/finding:
    - [ ] MUST emit only the deterministic violation/finding code and sorted diagnostics
    - [ ] MUST exit with code `1`
  - [ ] Diagnostics MUST be:
    - [ ] deduplicated
    - [ ] sorted by byte-order `strcmp`
    - [ ] deterministic in structure and ordering for equivalent input data; actual measured timing values MAY differ between executions and operating systems
    - [ ] free of raw argv, reconstructed command lines, child stdout/stderr, env values, absolute paths, raw payloads, source snippets, secrets, tokens, credentials, stack traces, and exception messages.
  - [ ] diagnostic ordering and formatting MUST be deterministic; measured timing values are not required to be identical across benchmark runs
  - [ ] diagnostic fields MUST use bounded ASCII-only labels and integer values
  - [ ] diagnostic formatting MUST NOT use `key=value`, URLs, paths, multiline payloads, or other values rejected by `ConsoleOutput`
  - [ ] diagnostic lines MUST use the existing ConsoleOutput-compatible single-line ASCII format
  - [ ] canonical diagnostic format is `<benchmark-id>: baseline_us:<integer> current_us:<integer> threshold_percent:<integer>`
  - [ ] the gate MUST NOT emit `unsafe-output` for any correctly validated diagnostic
  - [ ] malformed diagnostic data MUST fail closed rather than emit raw benchmark inputs

- [ ] `tools/config/performance.baseline.json` — canonical committed performance baseline:
  - [ ] the committed production baseline MUST be generated only through explicit `--update-baseline` execution on the pinned benchmark runner
  - [ ] isolated integration fixtures MAY generate temporary synthetic baselines through the same production gate entrypoint without a pinned runner
  - [ ] synthetic fixture baselines MUST NOT replace or modify the committed production baseline
  - [ ] synthetic fixture measurements MUST NOT be treated as authoritative performance evidence
  - [ ] the root map contains exactly:
    - [ ] `schema`
    - [ ] `protocol`
    - [ ] `runner`
    - [ ] `benchmarks`
  - [ ] `schema` is exactly integer `1`
  - [ ] `protocol` is exactly integer `1`
  - [ ] `runner` contains exactly:
    - [ ] `runner_class: string`
    - [ ] `os_family: string`
    - [ ] `architecture: string`
    - [ ] `php_version_id: int`
    - [ ] `php_int_size: int`
    - [ ] `php_sapi: string`
    - [ ] `php_config_sha256: string`
  - [ ] `runner_class` is the fixed documented benchmark runner class
  - [ ] CI runner selection MUST independently enforce the declared runner class
  - [ ] observable runtime fields MUST be derived from the executing PHP environment rather than arbitrary configuration values
  - [ ] `php_int_size` MUST be `8`
  - [ ] `php_sapi` MUST be `cli`
  - [ ] `php_config_sha256` MUST be derived from a fixed canonical map containing:
    - [ ] `memory_limit`
    - [ ] `realpath_cache_size`
    - [ ] `opcache.enable_cli`
    - [ ] `opcache.jit`
    - [ ] `opcache.jit_buffer_size`
    - [ ] loaded extension names and versions ordered by byte-wise `strcmp`
  - [ ] INI values MUST be read through `ini_get()` without including INI file paths
  - [ ] extension identities MUST be derived from the executing PHP runtime
  - [ ] PHP configuration fingerprinting MUST use `hash('sha256', Json::encodeStable($validatedConfiguration))`
  - [ ] the fingerprint MUST NOT include absolute paths, environment values, hostnames, credentials, or secrets
  - [ ] benchmark children MUST use the same `PHP_BINARY` as the gate process
  - [ ] the pinned runner MUST use a fixed PHP installation and configuration that do not depend on invocation-specific `PHPRC` or `PHP_INI_SCAN_DIR` overrides
  - [ ] the gate MUST reject unsupported PHP configuration overrides before benchmark execution
  - [ ] the benchmark child environment MUST preserve the documented PHP startup configuration invariants
  - [ ] observable parent runtime validation MUST NOT be misrepresented as independent verification of the effective child runtime configuration
  - [ ] `benchmarks` contains one entry per configured canonical benchmark id
  - [ ] every benchmark entry contains exactly:
    - [ ] `duration_us: int`
    - [ ] `fingerprint: string`
  - [ ] each `fingerprint` MUST contain exactly 64 lowercase hexadecimal characters
  - [ ] each `duration_us` MUST be within the supported positive integer duration bounds
  - [ ] unknown, missing, or additional fields MUST be rejected
  - [ ] baseline bytes are read through `DeterministicFile::readBytesExact()`
  - [ ] JSON decoding MUST use `JSON_THROW_ON_ERROR`
  - [ ] the decoded baseline MUST be validated against the exact required shapes and integer bounds
  - [ ] JSON encoding MUST use `Coretsia\Devtools\InternalToolkit\Json::encodeStable()`
  - [ ] canonical baseline bytes MUST be exactly `Json::encodeStable($validatedBaseline) . "\n"`
  - [ ] the baseline MUST use UTF-8, LF-only line endings, and exactly one final LF
  - [ ] re-encoding the validated decoded baseline MUST reproduce the original bytes exactly
  - [ ] non-canonical JSON, including duplicate object keys, MUST be rejected
  - [ ] maximum baseline file size is exactly `65536` bytes
  - [ ] the baseline MUST be a regular non-symlink file
  - [ ] file size MUST be checked through the existing suppressed-error boundary before reading
  - [ ] `DeterministicFile::readBytesExact()` MUST remain the canonical file-reading API
  - [ ] the actual returned byte length MUST be checked again before decoding
  - [ ] empty, oversized, missing, unreadable, or invalid baseline files MUST fail closed
  - [ ] no independent filesystem reader or baseline-specific IO abstraction may be introduced
  - [ ] JSON decoding MUST use an explicit maximum depth of `8`
  - [ ] JSON decoding MUST use native `json_decode($bytes, true, 8, JSON_THROW_ON_ERROR)`
  - [ ] decoded root, runner, and benchmark maps MUST be validated as non-empty associative arrays with their exact required keys
  - [ ] benchmark ids MUST be validated before canonical re-encoding
  - [ ] non-object JSON roots, arrays in place of required objects, and numeric benchmark identifiers MUST be rejected
  - [ ] byte-exact canonical re-encoding MUST remain the final check for duplicate keys, field ordering, and non-canonical JSON representations
  - [ ] decoding MUST NOT introduce another JSON parser or serialization abstraction
  - [ ] stores positive integer baseline durations in microseconds
  - [ ] rejects missing, duplicate, extra, invalid, or non-positive duration entries
  - [ ] uses the existing `DeterministicFile` for exact reading and atomic writing
  - [ ] MUST NOT implement an independent JSON encoder or recursive normalization policy
  - [ ] MUST NOT include raw command tokens, absolute paths, environment values, secrets, or diagnostic payloads
  - [ ] MUST be regenerated and reviewed when the canonical benchmark environment or benchmark cases change

- [ ] `.github/workflows/performance-benchmark.yml` — dedicated pinned-runner workflow:
  - [ ] uses an explicitly provisioned benchmark runner with stable, documented runner identity
  - [ ] runner selection MUST use dedicated benchmark-specific labels
  - [ ] the benchmark runner MUST NOT execute unrelated workloads during performance measurements
  - [ ] benchmark jobs MUST be serialized so multiple benchmark runs cannot compete for the same runner resources
  - [ ] workflow concurrency MUST NOT cancel an already running benchmark solely because another benchmark was queued
  - [ ] runner hardware and PHP configuration changes MUST require reviewed baseline regeneration
  - [ ] MUST NOT use `ubuntu-latest`, generic GitHub-hosted runners, or other shared-runner classes for threshold enforcement
  - [ ] installs the required pinned PHP and Composer toolchain
  - [ ] installs dependencies from the committed lockfile without updating package versions
  - [ ] the repository-root `composer.lock` MUST exist and be tracked before dependency installation
  - [ ] a missing lockfile MUST fail the benchmark job before Composer installation
  - [ ] dependency installation MUST NOT generate, regenerate, or modify `composer.lock`
  - [ ] the job MUST use `composer install`, never `composer update`, for benchmark dependency preparation
  - [ ] the dependency-installation step MUST declare `timeout-minutes: 10`
  - [ ] dependency-installation timeout MUST fail the job rather than continue with incomplete dependencies
  - [ ] the benchmark execution step MUST start only after successful dependency installation
  - [ ] validates the runner identity against the committed baseline before measuring commands
  - [ ] executes `composer --no-interaction performance:gate`
  - [ ] MUST NOT automatically update or commit baseline results
  - [ ] MUST NOT run untrusted pull-request code on a privileged self-hosted benchmark runner
  - [ ] workflow triggers MUST be restricted to trusted execution contexts
  - [ ] the benchmark runner MUST NOT expose publishing credentials or other unrelated secrets to benchmark processes
  - [ ] the job MUST declare `timeout-minutes: 45`
  - [ ] the job timeout MUST exceed the maximum configured benchmark execution and bounded cleanup budget, including dependency installation
  - [ ] the job MUST NOT rely on the GitHub Actions default maximum execution time
  - [ ] the job MUST fail when the pinned runner is unavailable or incompatible with the committed baseline
  - [ ] baseline updates MUST NOT be performed automatically by CI
  - [ ] remains separate from the generic `ci`, `gates`, `quality`, and `test` workflows

#### Modifies

- [ ] `composer.json` — add standalone performance gate script
  - [ ] `performance:gate` → `@php tools/gates/performance_gate.php`
  - [ ] MUST NOT add `performance:gate` to `gates` or `ci`

- [ ] `tools/support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_PERFORMANCE_DEGRADED` as the violation/finding code
  - [ ] `CORETSIA_PERFORMANCE_GATE_SCAN_FAILED` as the tooling-failure code
  - [ ] add both constants to the existing `REGISTRY` array
  - [ ] preserve existing error-code naming, ordering, and registry validation conventions

- [ ] add command `performance:gate` in `docs/guides/commands.md`

### Cross-cutting

#### Observability

- [ ] Gate output is tooling diagnostics, not runtime observability.
- [ ] Output contains only:
  - [ ] canonical benchmark id
  - [ ] baseline time
  - [ ] current time
  - [ ] threshold
- [ ] Output MUST NOT contain:
  - [ ] raw argv
  - [ ] a reconstructed command line
  - [ ] child stdout or stderr
  - [ ] environment values
  - [ ] paths
  - [ ] payloads
  - [ ] secrets or credentials

#### Errors

- [ ] Deterministic codes.

#### Security / Redaction

- [ ] Gate diagnostics are safe by construction.
- [ ] The gate does not depend on runtime `SensitiveDataRedactorInterface` or `platform/redaction`.
- [ ] Allowed diagnostic fields are limited to:
  - [ ] deterministic gate code
  - [ ] canonical benchmark id
  - [ ] baseline time
  - [ ] current time
  - [ ] threshold
- [ ] Forbidden diagnostic data:
  - [ ] raw argv
  - [ ] reconstructed command lines
  - [ ] child stdout or stderr
  - [ ] environment values
  - [ ] absolute paths
  - [ ] payloads
  - [ ] secrets
  - [ ] tokens
  - [ ] credentials
  - [ ] Throwable messages
  - [ ] stack traces

### Verification

- [ ] Synthetic integration tests MUST validate gate behavior without requiring a pinned benchmark runner.
- [ ] Real performance thresholds MUST be enforced only on the dedicated pinned runner.
- [ ] Before accepting the initial committed baseline, the complete benchmark suite MUST be executed at least three additional times on the same pinned runner without source, dependency, or configuration changes.
- [ ] All calibration executions MUST pass against the candidate baseline using the configured threshold.
- [ ] Benchmark cases showing false-positive degradation under unchanged conditions MUST be stabilized or excluded before the baseline is committed.
- [ ] Calibration MUST account for process-startup variance, polling granularity, and operating-system scheduling.
- [ ] Calibration results MUST NOT introduce runtime artifacts, production dependencies, or additional gate execution paths.
- [ ] Missing or incompatible baseline MUST fail closed without creating a new baseline.
- [ ] Child process failures, non-zero exit codes, timeouts, and cleanup failures MUST fail the gate.
- [ ] Default gate execution MUST leave the baseline unchanged.
- [ ] Explicit baseline update MUST produce canonical stable JSON through existing serialization and atomic file-writing boundaries.

### Tests

- [ ] `tools/tests/Unit/PerformanceBenchmarkMathTest.php`
  - [ ] verifies the exact median of five synthetic measurements
  - [ ] verifies ordering independence of the median calculation
  - [ ] verifies degradation strictly above the configured threshold
  - [ ] verifies equality with the threshold is accepted
  - [ ] verifies invalid durations and integer overflow are rejected
  - [ ] performs no process execution or wall-clock measurements

- [ ] `tools/tests/Integration/PerformanceGateTest.php`
  - [ ] uses isolated synthetic benchmark fixtures and controlled baseline values
  - [ ] MUST reuse the existing `ToolContractTestCase` fixture and temporary-directory conventions
  - [ ] synthetic integration fixtures MUST use an isolated temporary repository layout
  - [ ] fixture executions MUST use the same GateRuntime and production gate entrypoint
  - [ ] fixture repositories MUST reuse the existing gate-test bootstrap and autoload conventions
  - [ ] fixture autoload MUST make the real `Coretsia\Devtools\InternalToolkit\Json` implementation available
  - [ ] tests MUST NOT provide a replacement JSON encoder or mock the toolkit serialization behavior
  - [ ] fixture repositories MAY use synthetic root `coretsia` executables only to validate benchmark orchestration
  - [ ] synthetic fixture execution MUST NOT load production CLI runtime services
  - [ ] tests MUST preserve the same baseline encoding, fingerprint, and GateRuntime contracts as production gate execution
  - [ ] synthetic benchmark cases MUST execute only the fixture's canonical root `coretsia` entrypoint
  - [ ] tests MUST NOT introduce production `--fixture`, `--test-mode`, `--command`, `--binary`, or runtime configuration-path overrides
  - [ ] tests MUST NOT modify the committed performance baseline
  - [ ] exact median and threshold arithmetic MUST be verified independently through `PerformanceBenchmarkMathTest`
  - [ ] does not depend on real wall-clock thresholds or dedicated runner availability
  - [ ] verifies exactly one warmup and five measured child executions per benchmark case
  - [ ] verifies benchmark cases execute in their configured order
  - [ ] verifies measurement includes process startup and execution rather than only in-process command logic
  - [ ] verifies exact median and threshold arithmetic through `PerformanceBenchmarkMathTest`, not unstable wall-clock assertions
  - [ ] asserts `CORETSIA_PERFORMANCE_DEGRADED` and canonical benchmark id on regression
  - [ ] asserts stable diagnostic ordering and formatting
  - [ ] asserts exit code `1` on regression and `0` on pass
  - [ ] verifies missing, malformed, stale, or runner-incompatible baselines fail closed
  - [ ] verifies normal execution never creates or updates the baseline
  - [ ] verifies explicit baseline-update behavior and canonical JSON output
  - [ ] verifies a failure in a later benchmark case does not partially replace the existing baseline
  - [ ] verifies failed benchmark configuration validation does not modify the existing baseline
  - [ ] verifies rejected baseline update attempts leave the previously committed bytes unchanged
  - [ ] verifies successful baseline regeneration publishes all benchmark entries together
  - [ ] verifies no degradation diagnostics are emitted during successful baseline regeneration
  - [ ] verifies missing initial baseline is accepted only during explicit baseline update
  - [ ] verifies duplicate, unknown, and malformed gate arguments fail deterministically
  - [ ] verifies fixture-specific timeout configuration without production test-only arguments
  - [ ] verifies invalid benchmark argument-token shapes and bounds are rejected before process execution
  - [ ] verifies baseline schema, protocol, fingerprint, and runtime identity mismatches fail before benchmarking
  - [ ] verifies malformed and oversized baseline files fail through the existing GateRuntime boundary
  - [ ] verifies non-zero child exit, timeout, and failed cleanup never become successful measurements
  - [ ] verifies cleanup executes after every post-spawn failure path
  - [ ] verifies naturally exited children are not terminated again
  - [ ] verifies the child exit status captured through `proc_get_status()` remains authoritative independently of `proc_close()` results
  - [ ] verifies `proc_close()` returning `-1` is handled when observed, without requiring this result on every supported PHP/OS combination
  - [ ] verifies bounded termination and cleanup using synthetic short-lived and deliberately blocking PHP processes
  - [ ] deliberately blocking fixtures MUST use a test-owned bounded gate-process watchdog
  - [ ] the existing unbounded `ToolContractTestCase::runPhp()` MUST NOT be used to supervise deliberately blocking gate fixtures
  - [ ] the test watchdog MUST terminate a stuck gate-under-test within a fixed deadline and report a test failure
  - [ ] the test watchdog MUST also perform bounded cleanup of any remaining fixture-owned benchmark child processes
  - [ ] test cleanup MUST NOT terminate unrelated processes or unverified process ids
  - [ ] fixture-owned child processes MUST NOT remain running after test teardown
  - [ ] watchdog cleanup failures MUST fail the integration test
  - [ ] test supervision MUST NOT introduce process-control APIs or test-only flags into the production performance gate
  - [ ] verifies tooling failure takes precedence over previously accumulated performance-regression findings
  - [ ] verifies no orphan benchmark child remains after successful cleanup

- [ ] `tools/tests/Integration/PerformanceGateDoesNotLeakChildProcessDataTest.php`
  - [ ] child command writes fake secrets, env-like values, paths, payloads, and argv-like text to stdout and stderr
  - [ ] gate redirects child stdout and stderr directly to the platform null sink
  - [ ] no child stdout or stderr pipes are created
  - [ ] none of the fake values appears in gate diagnostics
  - [ ] raw command tokens are never reconstructed or rendered
  - [ ] only registered gate codes, canonical benchmark ids, integer timings, and integer thresholds are emitted
  - [ ] diagnostics remain compatible with the existing `ConsoleOutput` sanitization policy
  - [ ] subprocess startup, failure, timeout, and cleanup paths do not leak child diagnostics

### DoD

- [ ] Gate implemented using the existing `GateRuntime` execution boundary.
- [ ] No duplicate gate bootstrap, repository-path resolution, JSON encoder, diagnostic sorter, or generic error-rendering layer is introduced.
- [ ] Performance gate infrastructure depends only on existing tooling support and devtools utilities.
- [ ] Canonical benchmark fingerprints and baseline JSON use `Coretsia\Devtools\InternalToolkit\Json`.
- [ ] No production Foundation, Kernel, or Platform implementation is imported by the performance gate infrastructure.
- [ ] Canonical baseline bytes use the toolkit encoder with exactly one final LF.
- [ ] Baseline decoding and byte-exact canonical validation introduce no second JSON implementation.
- [ ] Gate implementation follows existing `tools/gates` source structure and uses `tools/support` ownership boundaries.
- [ ] Benchmark arithmetic is independently verified through pure tooling unit tests.
- [ ] Benchmark case fingerprints prevent comparisons against unrelated command definitions.
- [ ] Baseline loading rejects non-canonical JSON and duplicate object keys.
- [ ] Initial baseline creation works without requiring a pre-existing baseline.
- [ ] Windows and POSIX process termination and exit-status handling are covered.
- [ ] No production test-only arguments or fixture execution paths are introduced.
- [ ] Benchmark configuration and committed baseline schemas are strictly validated.
- [ ] Initial baseline generated and committed from the actual pinned benchmark runner.
- [ ] Dedicated pinned-runner CI workflow configured and verified.
- [ ] Performance gate is excluded from generic `composer gates` and `composer ci`.
- [ ] Normal gate execution never creates or modifies the baseline.
- [ ] Explicit baseline update uses existing stable JSON encoding and atomic file writing.
- [ ] Measurements use monotonic time, fixed warmups, fixed repetitions, and median aggregation.
- [ ] Child execution, exit-status validation, timeouts, and cleanup are bounded.
- [ ] Child stdout and stderr never enter the gate process or diagnostics.
- [ ] Diagnostics expose only registered gate codes, canonical benchmark ids, integer timings, and integer thresholds.
- [ ] Diagnostics never expose raw argv, reconstructed command lines, child output, env values, absolute paths, payloads, secrets, or Throwable details.
- [ ] Synthetic integration tests run without a dedicated runner or machine-dependent timing assertions.
- [ ] Gate behavior is verified for regression, success, invalid baseline, runner mismatch, process failure, timeout, and cleanup failure.
- [ ] The gate requires no new runtime package, Kernel service, or runtime redaction implementation.
- [ ] Existing repository gates, architecture checks, and tooling tests remain green.

---

### PERF.30.0 HTTP Performance Benchmarking Harness (MUST) [TOOLING]

---
type: tools
phase: PERF
track: performance
epic_id: "PERF.30.0"
owner_path: "tools/benchmarks/http/"

execution_policy: deferred
required_for_product_release: false
activation: explicit
eligible_after:
- "3.40.0"
- "3.50.0"
- "3.130.0"
- "3.140.0"
- "3.145.0"

goal: "Забезпечити відкладене контрольоване вимірювання продуктивності HTTP runtime для canonical micro-сценаріїв, з незалежним pinned-runner baseline та regression gating після готовності необхідних production-компонентів, без блокування product releases."
provides:
- "Reference HTTP benchmarks for canonical scenarios: hello-world, JSON request/response, problem-details rendering"
- "Pinned-environment baseline + CI gate for regression detection"
- "Deterministic benchmark reports and comparison rules reused by later performance epics"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/metrics-policy.md"
- "docs/ssot/http-middleware-catalog.md"
---

- [ ] update `PERF.30.0 HTTP Performance Benchmarking Harness` to ensure benchmark output does not include raw payloads, tokens, env values, or absolute local paths.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 3.40.0 — problem-details runtime exists for benchmark scenario
  - 3.50.0 — HTTP runtime pipeline exists
  - 3.130.0 — routing runtime exists
  - 3.140.0 — http-app runtime exists
  - 3.145.0 — browser-visible web app entrypoint exists
  - PERF.10.0 — shared benchmark toolkit foundation exists

- Required deliverables (exact paths):
  - `packages/platform/http/` — HTTP runtime under test
  - `packages/platform/problem-details/` — problem-details scenario under test
  - `packages/platform/routing/` — compiled/runtime routing scenario under test
  - `packages/platform/http-app/` — action invocation scenario under test
  - N/A beyond the runtime packages under test.
  - This epic creates and owns its dedicated benchmark fixture app:
    - `tools/tests/Fixtures/HttpBenchApp/`

- Required config roots/keys:
  - `http.*` — HTTP runtime config used by fixture app
  - `problem_details.*` — problem-details scenario config
  - `app.id` — cache/app isolation for fixture benchmark app

- Required tags:
  - `http.middleware.system_pre`
  - `http.middleware.system`
  - `http.middleware.system_post`
  - `http.middleware.app_pre`
  - `http.middleware.app`
  - `http.middleware.app_post`
  - `http.middleware.route_pre`
  - `http.middleware.route`
  - `http.middleware.route_post`

- Required contracts / ports:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `devtools/internal-toolkit` (canonical tooling JSON writer / deterministic path helpers for benchmark report generation)

Forbidden:
- runtime imports from `packages/**/src/**` by path (`require/include` with package src literals)
- live network calls during benchmark execution
- non-pinned shared-runner gating as source of truth

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - `composer benchmark:http` → `tools/benchmarks/http/run.php`
  - `composer benchmark:http:gate` → `tools/gates/http_benchmark_gate.php`

- HTTP:
  - benchmark scenarios MUST cover:
    - `GET /__bench/hello`
    - `POST /__bench/json`
    - `GET /__bench/problem`

- Kernel hooks/tags:
  - N/A

- Other runtime discovery / integration tags:
  - N/A

- Artifacts:
  - reads: `tools/benchmarks/http/http.baseline.json`
  - writes: `tools/benchmarks/http/http.report.json`

- Notes (only if applicable):
  - Gate MUST fail only in a dedicated pinned benchmark job.
  - Generic CI MAY run the harness in smoke/advisory mode, but MUST NOT be the release gate source of truth.

### Deliverables (MUST)

#### Creates

- [ ] `tools/benchmarks/http/run.php` — canonical benchmark runner
- [ ] `tools/benchmarks/http/BenchmarkConfig.php` — warmups/runs/scenarios/environment fingerprint policy
- [ ] `tools/benchmarks/http/http.baseline.json` — pinned-runner baseline
- [ ] `tools/benchmarks/http/http.report.schema.json` — report shape lock
- [ ] `tools/benchmarks/http/README.md` — benchmark methodology + scenario list
- [ ] `tools/gates/http_benchmark_gate.php` — baseline comparator gate
  - [ ] MUST use `tools/support/ConsoleOutput.php` only
  - [ ] line1 MUST be a deterministic CODE
  - [ ] line2+ MUST contain only safe diagnostics sorted `strcmp`
  - [ ] MUST NOT print absolute paths, raw payloads, headers, cookies, query strings, or stack traces
- [ ] `tools/tests/Integration/Benchmarks/HttpBenchmarkHarnessTest.php` — harness proof
- [ ] `tools/tests/Integration/Gates/HttpBenchmarkGateTest.php` — deterministic gate proof
- [ ] `tools/tests/Unit/Benchmarks/HttpBenchmarkConfigTest.php`
- [ ] `tools/tests/Unit/Benchmarks/HttpBenchmarkReportSchemaTest.php`
- [ ] `tools/tests/Contract/Benchmarks/HttpBenchmarkOutputDoesNotLeakTest.php`
- [ ] `tools/tests/Fixtures/HttpBenchApp/` — minimal benchmark app fixture
- [ ] `docs/ops/http-benchmarks.md` — how to run/update baseline and how CI interprets results

#### Modifies

- [ ] `composer.json` — register standalone tooling scripts:
  - [ ] `benchmark:http` → `@php tools/benchmarks/http/run.php`
  - [ ] `benchmark:http:gate` → `@php tools/gates/http_benchmark_gate.php`
  - [ ] MUST NOT add real performance gating to generic `gates` or `ci`.
- [ ] `.github/workflows/performance-http.yml` — standalone pinned HTTP benchmark workflow
- [ ] `tools/support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_HTTP_BENCHMARK_DEGRADED`
  - [ ] `CORETSIA_HTTP_BENCHMARK_BASELINE_MISSING`
  - [ ] `CORETSIA_HTTP_BENCHMARK_ENV_MISMATCH`
  - [ ] `CORETSIA_HTTP_BENCHMARK_RUN_FAILED`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `tools/benchmarks/http/BenchmarkConfig.php`
- [ ] Keys (dot):
  - [ ] `benchmark.http.warmup_runs` = 3
  - [ ] `benchmark.http.measure_runs` = 9
  - [ ] `benchmark.http.metric` = `median_wall_ms`
  - [ ] `benchmark.http.threshold_multiplier` = 1.15
  - [ ] `benchmark.http.require_env_fingerprint_match` = true
- [ ] Rules:
  - [ ] baseline MUST include PHP version, SAPI, opcache/JIT state, OS fingerprint, CPU class label

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `tools/benchmarks/http/http.report.json` (deterministic key order, LF-only, final newline)
  - [ ] report JSON bytes MUST be produced via the canonical tooling JSON writer from `coretsia/devtools-internal-toolkit`
  - [ ] ad-hoc `json_encode` MUST NOT be used in benchmark tooling for baseline/report files
- [ ] Reads:
  - [ ] validates baseline/report shape before compare

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] benchmark runner MUST boot a fresh app/kernel per measured sample OR prove equivalent reset-safe isolation

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] tool output only; no runtime metrics are required by this epic
- [ ] Logs:
  - [ ] benchmark output MUST contain only scenario name, run counts, median timings, threshold, outcome
  - [ ] MUST NOT dump raw request bodies, headers, cookies, query strings, or stack traces

#### Errors

- Exceptions introduced:
  - N/A
- [ ] Mapping:
  - [ ] deterministic tool exit codes via `ConsoleOutput` + registered error codes

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw payloads
  - [ ] raw headers/cookies
  - [ ] absolute paths
- [ ] Allowed:
  - [ ] scenario ids
  - [ ] safe counts
  - [ ] median/p95 numbers
  - [ ] environment class/fingerprint

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `tools/tests/Integration/Benchmarks/HttpBenchmarkHarnessTest.php`
  (asserts: safe output shape only; no raw payloads)
- [ ] If redaction exists → `tools/tests/Integration/Gates/HttpBenchmarkGateTest.php`
  (asserts: absolute paths and raw payloads never appear)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `tools/tests/Fixtures/HttpBenchApp/`
- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `tools/tests/Unit/Benchmarks/HttpBenchmarkConfigTest.php`
  - [ ] `tools/tests/Unit/Benchmarks/HttpBenchmarkReportSchemaTest.php`
- Contract:
  - [ ] `tools/tests/Contract/Benchmarks/HttpBenchmarkOutputDoesNotLeakTest.php`
- Integration:
  - [ ] `tools/tests/Integration/Benchmarks/HttpBenchmarkHarnessTest.php`
  - [ ] `tools/tests/Integration/Gates/HttpBenchmarkGateTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] pinned benchmark CI job wired and isolated

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] baseline/report files are stable bytes
  - [ ] baseline/report JSON serialization MUST use the same canonical tooling JSON writer for both generation and comparison fixtures
  - [ ] scenario list order is fixed
  - [ ] comparison logic is deterministic
- [ ] Gate fails only on pinned benchmark environment
- [ ] Docs updated:
  - [ ] `tools/benchmarks/http/README.md`
  - [ ] `docs/ops/http-benchmarks.md`

---

### PERF.40.0 Core Kernel + Foundation Hot-path Optimization (MUST) [IMPL+TOOLING]

---
type: tools
phase: PERF
track: performance
epic_id: "PERF.40.0"
owner_path: "tools/benchmarks/core/"

execution_policy: deferred
required_for_product_release: false
activation: explicit
eligible_after:
- "1.200.0"
- "1.280.0"
- "1.310.0"
- "1.330.0"

goal: "Оптимізувати найгарячіші internal hot paths у core/foundation та core/kernel без зміни семантики, а факт виграшу довести стабільними microbenchmarks і downstream HTTP benchmarks."
provides:
- "Core microbenchmark suite for container, TagRegistry, StableJsonEncoder, config/runtime hot paths"
- "Concrete optimization work in foundation/kernel hot paths with behavior-lock tests"
- "Performance regression thresholds tied to deterministic benchmark methodology"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - 1.200.0 — Foundation DI container + TagRegistry + reset orchestration baseline exists
  - 1.280.0 — KernelRuntime exists
  - 1.310.0 — ConfigKernel/config merge runtime exists
  - 1.330.0 — compiled container artifact exists
  - PERF.10.0 — shared benchmark toolkit foundation exists

- Required deliverables (exact paths):
  - `packages/core/foundation/src/` — Foundation hot-path implementation root
  - `packages/core/kernel/src/` — Kernel hot-path implementation root
  - `packages/core/foundation/tests/` — existing behavior contracts that MUST remain green
  - `packages/core/kernel/tests/` — existing behavior contracts that MUST remain green

- Required config roots/keys:
  - `foundation.*` — foundation runtime config
  - `kernel.*` — kernel runtime/config compile behavior
  - `app.id` — artifact/cache isolation

- Required tags:
  - `kernel.reset`
  - `kernel.stateful`
  - `kernel.hook.before_uow`
  - `kernel.hook.after_uow`

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (tools epic that modifies existing core packages)

Forbidden:

- semantic changes to public contracts as part of optimization
- weakening security-sensitive hashing/policy under the pretext of performance
- adding non-deterministic caches keyed by wall-clock/random data

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - `Coretsia\Foundation\Tag\TagRegistry`

### Entry points / integration points (MUST)

- CLI:
  - `composer benchmark:core` → `tools/benchmarks/core/run.php`
  - `composer benchmark:core:gate` → `tools/gates/core_performance_gate.php`

- HTTP:
  - N/A

- Kernel hooks/tags:
  - optimized services that remain stateful MUST still implement `ResetInterface` and remain reset-discoverable

- Other runtime discovery / integration tags:
  - N/A

- Artifacts:
  - reads: `tools/benchmarks/core/core.baseline.json`
  - writes: `tools/benchmarks/core/core.report.json`

### Deliverables (MUST)

#### Creates

- [ ] `tools/benchmarks/core/run.php` — core microbenchmark runner
- [ ] `tools/benchmarks/core/CoreBenchmarkConfig.php` — scenario list + methodology
- [ ] `tools/benchmarks/core/core.baseline.json` — baseline for pinned runner
- [ ] `tools/gates/core_performance_gate.php` — regression comparator
- [ ] `tools/tests/Integration/Benchmarks/CoreBenchmarkHarnessTest.php`
- [ ] `tools/tests/Integration/Gates/CorePerformanceGateTest.php`
- [ ] `docs/architecture/performance-core.md` — hot-path inventory + optimization rules

#### Modifies

- [ ] `packages/core/foundation/src/` — optimize container/registry/serialization hot paths without changing output semantics
- [ ] `packages/core/kernel/src/` — optimize runtime/config/artifact hot paths without changing output semantics
- [ ] `packages/core/foundation/tests/` — add behavior-lock proofs where optimization could drift semantics
- [ ] `packages/core/kernel/tests/` — add behavior-lock proofs where optimization could drift semantics
- [ ] `framework/composer.json` — add scripts:
  - [ ] `benchmark:core`
  - [ ] `benchmark:core:gate`
- [ ] `composer.json` — add mirror scripts delegating to `framework/`
- [ ] `.github/workflows/performance-core.yml` — standalone pinned Core benchmark workflow
- [ ] `tools/support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_CORE_PERFORMANCE_DEGRADED`
  - [ ] `CORETSIA_CORE_PERFORMANCE_RUN_FAILED`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `tools/benchmarks/core/CoreBenchmarkConfig.php`
- [ ] Keys (dot):
  - [ ] `benchmark.core.scenarios.container_get` = true
  - [ ] `benchmark.core.scenarios.tag_registry_all` = true
  - [ ] `benchmark.core.scenarios.stable_json_encode` = true
  - [ ] `benchmark.core.scenarios.kernel_boot_minimal` = true
  - [ ] `benchmark.core.threshold_multiplier` = 1.10
- [ ] Rules:
  - [ ] every scenario MUST preserve identical public output bytes/ordering vs pre-optimization behavior

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- ServiceProvider wiring evidence:
  - N/A

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `tools/benchmarks/core/core.report.json` (deterministic bytes)
- [ ] Reads:
  - [ ] validates baseline/report shape before comparison

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] any newly added caches/buffers in runtime services MUST either be immutable or reset-safe
  - [ ] if mutable and long-running relevant, service MUST implement `ResetInterface`
  - [ ] such services MUST remain discoverable via Foundation reset policy

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] benchmark/gate output MUST contain only scenario ids, medians, thresholds, outcome
  - [ ] MUST NOT include config dumps, raw payloads, or absolute paths

#### Errors

- Exceptions introduced:
  - N/A
- [ ] Mapping:
  - [ ] deterministic tool exit codes only

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secrets
  - [ ] raw payloads
  - [ ] absolute paths
- [ ] Allowed:
  - [ ] safe scenario ids
  - [ ] counts
  - [ ] timing numbers

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If `kernel.reset` used → existing and new reset tests remain green after optimization
- [ ] If logs exist → harness/gate tests assert safe output only

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `tools/tests/Unit/Benchmarks/CoreBenchmarkConfigTest.php`
- Contract:
  - [ ] `packages/core/foundation/tests/Contract/OptimizationPreservesDeterministicOutputContractTest.php`
  - [ ] `packages/core/kernel/tests/Contract/OptimizationPreservesArtifactBytesContractTest.php`
- Integration:
  - [ ] `tools/tests/Integration/Benchmarks/CoreBenchmarkHarnessTest.php`
  - [ ] `tools/tests/Integration/Gates/CorePerformanceGateTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] public behavior is unchanged
  - [ ] artifact bytes/order stay stable
  - [ ] benchmark reports are deterministic
- [ ] Downstream proof:
  - [ ] Core benchmark results demonstrate the effect of implemented optimizations against the accepted baseline.
  - [ ] If PERF.30.0 is available, relevant downstream HTTP benchmarks MUST be executed and MUST NOT show unacceptable regressions.
  - [ ] If PERF.30.0 is unavailable, its absence MUST NOT block this epic; downstream HTTP verification MAY be completed separately after PERF.30.0 activation.
- [ ] Docs updated:
  - [ ] `docs/architecture/performance-core.md`

---

### PERF.50.0 Performance Gates for Database and Filesystem (SHOULD) [TOOLING]

---
type: tools
phase: PERF
track: performance
epic_id: "PERF.50.0"
owner_path: "tools/gates/"

execution_policy: deferred
required_for_product_release: false
activation: explicit
eligible_after:
- "0.50.0"
- "4.30.0"
- "4.60.0"
- "4.70.0"
- "4.90.0"

goal: "Додати pinned-runner performance gates для типових database та filesystem операцій, щоб бачити регресії від змін драйверів, query/path shaping або IO policy до виходу релізу."
provides:
- "Reference DB benchmarks for minimal round-trip operations"
- "Reference filesystem benchmarks for read/write/stat operations"
- "Dedicated gate and baseline policy aligned with CLI/HTTP/core performance gates"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.50.0 — deterministic file IO policy exists
  - 4.30.0 — filesystem implementation exists
  - 4.60.0 — platform/database core exists
  - 4.70.0 — SQLite database driver exists
  - 4.90.0 — migrations exist
  - PERF.10.0 — shared benchmark toolkit foundation exists

- Required deliverables (exact paths):
  - `packages/platform/database/` — database runtime under test
  - `packages/platform/migrations/` — schema setup for DB benchmark fixture
  - `tools/support/` — canonical deterministic tooling helpers

- Required config roots/keys:
  - `database.default`
  - `database.connections.*`
  - `app.id`

- Required tags:
  - none

- Required contracts / ports:
  - `PDO`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (tools-only gate/harness)

Forbidden:

- live external database services in CI baseline job
- non-deterministic temporary path handling
- printing raw SQL text or absolute file paths in gate output

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`

### Entry points / integration points (MUST)

- CLI:
  - `composer benchmark:io`
  - `composer benchmark:io:gate`

- HTTP:
  - N/A

- Kernel hooks/tags:
  - N/A

- Other runtime discovery / integration tags:
  - N/A

- Artifacts:
  - reads: `tools/config/io_performance.baseline.json`
  - writes: `tools/config/io_performance.report.json`

### Deliverables (MUST)

#### Creates

- [ ] `tools/config/io_performance.php` — benchmark scenarios + thresholds
- [ ] `tools/config/io_performance.baseline.json` — pinned-runner baseline
- [ ] `tools/gates/database_filesystem_performance_gate.php` — comparator gate
- [ ] `tools/benchmarks/io/run.php` — benchmark runner
- [ ] `tools/tests/Integration/Gates/DatabaseFilesystemPerformanceGateTest.php`
- [ ] `tools/tests/Integration/Benchmarks/IoBenchmarkHarnessTest.php`
- [ ] `tools/tests/Fixtures/IoBench/` — SQLite DB + temp fs fixture root
- [ ] `docs/ops/performance-db-fs.md` — methodology + safe interpretation guide

#### Modifies

- [ ] `framework/composer.json` — add scripts:
  - [ ] `benchmark:io`
  - [ ] `benchmark:io:gate`
- [ ] `composer.json` — add mirror scripts delegating to `framework/`
- [ ] `.github/workflows/performance-io.yml` — standalone pinned IO benchmark workflow
- [ ] `tools/support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_DBFS_PERFORMANCE_DEGRADED`
  - [ ] `CORETSIA_DBFS_PERFORMANCE_RUN_FAILED`
  - [ ] `CORETSIA_DBFS_BENCHMARK_ENV_MISMATCH`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `tools/config/io_performance.php`
- [ ] Keys (dot):
  - [ ] `benchmark.io.database.driver` = `sqlite`
  - [ ] `benchmark.io.database.scenarios.select_one` = true
  - [ ] `benchmark.io.database.scenarios.insert_and_rollback` = true
  - [ ] `benchmark.io.filesystem.scenarios.read_small_file` = true
  - [ ] `benchmark.io.filesystem.scenarios.write_small_file` = true
  - [ ] `benchmark.io.filesystem.scenarios.stat_existing_file` = true
  - [ ] `benchmark.io.threshold_multiplier` = 1.20
- [ ] Rules:
  - [ ] DB benchmark fixture MUST be local SQLite-first in CI
  - [ ] FS benchmark fixture MUST use repo-local temp root with normalized paths only

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `tools/config/io_performance.report.json` (deterministic bytes)
- [ ] Reads:
  - [ ] validates report/baseline schema before compare

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] temporary DB/filesystem fixtures MUST be recreated or cleaned deterministically between measured runs

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] output only operation ids, medians, threshold, outcome
  - [ ] MUST NOT print raw SQL or absolute paths

#### Errors

- Exceptions introduced:
  - N/A
- [ ] Mapping:
  - [ ] deterministic tool exit codes only

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw SQL
  - [ ] DSNs with credentials
  - [ ] absolute paths
- [ ] Allowed:
  - [ ] driver id
  - [ ] safe operation ids
  - [ ] timing numbers

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If logs exist → gate/harness tests assert no raw SQL and no absolute paths
- [ ] If redaction exists → `tools/tests/Integration/Gates/DatabaseFilesystemPerformanceGateTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture root:
  - [ ] `tools/tests/Fixtures/IoBench/`
- [ ] Fake adapters:
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `tools/tests/Unit/Benchmarks/IoPerformanceConfigTest.php`
- Contract:
  - [ ] `tools/tests/Contract/Benchmarks/IoBenchmarkOutputDoesNotLeakTest.php`
- Integration:
  - [ ] `tools/tests/Integration/Benchmarks/IoBenchmarkHarnessTest.php`
  - [ ] `tools/tests/Integration/Gates/DatabaseFilesystemPerformanceGateTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] fixture setup/cleanup is deterministic
  - [ ] baseline/report shapes are stable
- [ ] Gate runs only on pinned environment for fail/pass source of truth
- [ ] Docs updated:
  - [ ] `docs/ops/performance-db-fs.md`

---

### PERF.60.0 Asynchronous Performance Tuning (SHOULD) [IMPL+TOOLING]

---
type: tools
phase: PERF
track: performance
epic_id: "PERF.60.0"
owner_path: "tools/benchmarks/async/"

execution_policy: deferred
required_for_product_release: false
activation: explicit
eligible_after:
- "5.40.0"
- "5.60.0"
- "5.80.0"
- "5.100.0"
- "5.110.0"

goal: "Зацементувати async benchmark suite для events/queue/scheduler та прибрати найдорогіші накладні витрати на dispatch, serialization/deserialization і UoW-boundary без порушення determinism та redaction policy."
provides:
- "Reference async benchmarks for event dispatch, deferred flush, queue dispatch/worker, scheduler run"
- "Pinned-runner performance baselines for asynchronous subsystems"
- "Concrete tuning targets for payload shaping, worker loop overhead and scheduler execution path"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/metrics-policy.md"
- "docs/ssot/observability-and-errors.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - 5.40.0 — events runtime exists
  - 5.60.0 — queue core exists
  - 5.80.0 — queue CLI commands exist
  - 5.100.0 — command bus exists
  - 5.110.0 — scheduler runtime exists
  - PERF.10.0 — shared benchmark toolkit foundation exists

- Required deliverables (exact paths):
  - `packages/platform/events/`
  - `packages/platform/queue/`
  - `packages/platform/cqrs/`
  - `packages/platform/scheduler/`
  - `packages/core/kernel/tests/Fixtures/EnterpriseApp/`

- Required config roots/keys:
  - `events.*`
  - `queue.*`
  - `scheduler.*`
  - `app.id`

- Required tags:
  - `events.listener`
  - `kernel.hook.after_uow`
  - `cli.command`
  - `scheduler.schedule`

- Required contracts / ports:
  - `Coretsia\Contracts\Events\EventDispatcherInterface`
  - `Coretsia\Contracts\Queue\QueueInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleRunnerInterface`
  - `Coretsia\Contracts\Bus\CommandBusInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (tools epic driving benchmark/tuning work)

Forbidden:

- emitting raw event/job payloads in benchmark output
- introducing non-reset-safe mutable caches into worker/runtime state
- using shared-runner CI timings as release gate source of truth

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Events\EventDispatcherInterface`
  - `Coretsia\Contracts\Queue\QueueInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleRunnerInterface`
  - `Coretsia\Contracts\Bus\CommandBusInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`

### Entry points / integration points (MUST)

- CLI:
  - `composer benchmark:async`
  - `composer benchmark:async:gate`

- HTTP:
  - N/A

- Kernel hooks/tags:
  - benchmark suite MUST cover:
    - `events.listener`
    - `kernel.hook.after_uow`
    - `scheduler.schedule`

- Other runtime discovery / integration tags:
  - `cli.command` — queue/scheduler commands may be used as benchmark entrypoints

- Artifacts:
  - reads: `tools/benchmarks/async/async.baseline.json`
  - writes: `tools/benchmarks/async/async.report.json`

### Deliverables (MUST)

#### Creates

- [ ] `tools/benchmarks/async/run.php` — canonical async benchmark runner
- [ ] `tools/benchmarks/async/AsyncBenchmarkConfig.php` — scenario list + methodology
- [ ] `tools/benchmarks/async/async.baseline.json` — pinned baseline
- [ ] `tools/gates/async_performance_gate.php` — comparator gate
- [ ] `tools/tests/Integration/Benchmarks/AsyncBenchmarkHarnessTest.php`
- [ ] `tools/tests/Integration/Gates/AsyncPerformanceGateTest.php`
- [ ] `docs/architecture/async-performance.md` — tuning guide + benchmark interpretation

#### Modifies

- [ ] `packages/platform/events/src/` — reduce dispatch/deferred overhead without semantic drift
- [ ] `packages/platform/queue/src/` — reduce serialization/worker overhead without semantic drift
- [ ] `packages/platform/scheduler/src/` — reduce schedule enumeration/run overhead without semantic drift
- [ ] `packages/platform/events/tests/` — behavior-lock tests for tuned paths
- [ ] `packages/platform/queue/tests/` — behavior-lock tests for tuned paths
- [ ] `packages/platform/scheduler/tests/` — behavior-lock tests for tuned paths
- [ ] `framework/composer.json` — add scripts:
  - [ ] `benchmark:async`
  - [ ] `benchmark:async:gate`
- [ ] `composer.json` — add mirror scripts delegating to `framework/`
- [ ] `.github/workflows/performance-async.yml` — standalone pinned Async benchmark workflow
- [ ] `tools/support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_ASYNC_PERFORMANCE_DEGRADED`
  - [ ] `CORETSIA_ASYNC_PERFORMANCE_RUN_FAILED`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `tools/benchmarks/async/AsyncBenchmarkConfig.php`
- [ ] Keys (dot):
  - [ ] `benchmark.async.events.dispatch_sync` = true
  - [ ] `benchmark.async.events.flush_deferred` = true
  - [ ] `benchmark.async.queue.dispatch_sync` = true
  - [ ] `benchmark.async.queue.worker_once` = true
  - [ ] `benchmark.async.scheduler.run_one_due_task` = true
  - [ ] `benchmark.async.threshold_multiplier` = 1.15
- [ ] Rules:
  - [ ] scenarios MUST benchmark both empty/light and representative listener/job/task counts
  - [ ] payloads used in scenarios MUST remain json-like and redaction-safe

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `tools/benchmarks/async/async.report.json` (deterministic bytes)
- [ ] Reads:
  - [ ] validates report/baseline schema before compare

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] tuned runtime services with mutable state MUST implement `ResetInterface`
  - [ ] worker loop and deferred queues MUST prove no state leak across runs/UoW

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] benchmark output MUST contain only scenario ids, counts, medians, thresholds, outcome
  - [ ] MUST NOT include raw event payloads, job payloads, or schedule arguments

#### Errors

- Exceptions introduced:
  - N/A
- [ ] Mapping:
  - [ ] deterministic tool exit codes only

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw event/job payloads
  - [ ] secrets/PII
  - [ ] absolute paths
- [ ] Allowed:
  - [ ] safe scenario ids
  - [ ] counts
  - [ ] medians

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If `kernel.reset` used → events/queue/scheduler integration tests MUST fail if reset discipline is removed
- [ ] If logs exist → async harness/gate tests assert no raw payload leakage
- [ ] If redaction exists → `tools/tests/Integration/Gates/AsyncPerformanceGateTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `packages/core/kernel/tests/Fixtures/EnterpriseApp/`
- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `tools/tests/Unit/Benchmarks/AsyncBenchmarkConfigTest.php`
- Contract:
  - [ ] `packages/platform/queue/tests/Contract/TuningPreservesSerializationPolicyContractTest.php`
  - [ ] `packages/platform/events/tests/Contract/TuningPreservesDeferredFlushPolicyContractTest.php`
- Integration:
  - [ ] `tools/tests/Integration/Benchmarks/AsyncBenchmarkHarnessTest.php`
  - [ ] `tools/tests/Integration/Gates/AsyncPerformanceGateTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] payload shaping remains stable
  - [ ] UoW/reset semantics remain stable
  - [ ] benchmark reports are stable bytes
- [ ] Downstream proof:
  - [ ] representative async scenarios show neutral or improved medians on pinned runner
- [ ] Docs updated:
  - [ ] `docs/architecture/async-performance.md`

---

### PERF.70.0 Production Performance Profiling & Observability (SHOULD) [IMPL+DOC]

---
type: package
phase: PERF
track: performance
epic_id: "PERF.70.0"
owner_path: "packages/platform/profiling/"

package_id: "platform/profiling"
composer: "coretsia/platform-profiling"
kind: runtime
module_id: "platform.profiling"

execution_policy: deferred
required_for_product_release: false
activation: explicit
eligible_after:
- "1.280.0"
- "3.20.0"
- "3.30.0"
- "3.35.0"
- "3.50.0"

goal: "Дати production-safe profiling layer, який вмикається через config/policy, інтегрується з observability ports та external profilers без жорсткого runtime coupling, а також фіксує рекомендації для FPM/RoadRunner/Swoole tuning."
provides:
- "Production-safe profiling runtime with null, xhprof-style, and optional external bridge drivers"
- "Deterministic integer-only sampling policy for supported UoW lifecycles"
- "Exactly-once profiling start/stop lifecycle through Kernel hooks"
- "Optional profiling export through existing profiling contracts"
- "UoW-aware profiling hooks and optional HTTP trigger middleware"
- "Normalized deterministic profile summary outputs plus runtime tuning docs"
- "Independent deferred profiling capability without product release blocking"

tags_introduced: []
config_roots_introduced:
- "profiling"
artifacts_introduced:
- "profile_summary@1"

adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/http-middleware-catalog.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - 3.20.0 — logging baseline exists
  - 3.30.0 — tracing baseline exists
  - 3.35.0 — metrics baseline exists
  - 3.50.0 — HTTP runtime exists
  - 1.280.0 — KernelRuntime exists

- Required deliverables (exact paths):
  - `packages/core/kernel/` — UoW lifecycle hooks source
  - `packages/platform/http/` — HTTP integration point via tags
  - `docs/ssot/config-roots.md` — root registry to extend

- Required config roots/keys:
  - `profiling.*` — this epic introduces and owns
  - `http.*` — only for middleware integration toggle/placement
  - `app.id`

- Required tags:
  - `kernel.hook.before_uow`
  - `kernel.hook.after_uow`
  - `http.middleware.system_pre`
  - `cli.command`

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface`
  - `Coretsia\Contracts\Observability\Profiling\ProfileExporterInterface`
  - `Coretsia\Contracts\Observability\Profiling\ProfileArtifact`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Server\MiddlewareInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/http` as compile-time dependency
- `platform/cli` as compile-time dependency
- hard dependency on external SaaS/vendor SDKs in the core runtime package
- storing raw profiler payloads as canonical Coretsia artifacts

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
- Foundation stable APIs:
  - `Coretsia\Contracts\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - `profile:explain` → `platform/profiling` `src/Console/ProfileExplainCommand.php`
  - `profile:prune` → `platform/profiling` `src/Console/ProfilePruneCommand.php`

- HTTP:
  - middleware slots/tags:
    - `http.middleware.system_pre` priority `520` meta `per owner schema`

- Kernel hooks/tags:
  - `kernel.hook.before_uow` priority `200` meta `per owner schema`
  - `kernel.hook.after_uow` priority `-200` meta `per owner schema`

- Other runtime discovery / integration tags:
  - `cli.command` priority `0` meta `per owner schema`

- Artifacts:
  - writes: `packages/applications/skeleton/var/profiling/<appId>/summary-*.json`
  - reads: N/A

- Notes (only if applicable):
  - Raw vendor-specific profile payloads, if emitted, are debug outputs only and MUST NOT be treated as Coretsia artifacts.
  - Only normalized summaries written by this package are deterministic/Coretsia-owned.

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/profiling/composer.json`
- [ ] `packages/platform/profiling/src/Module/ProfilingModule.php`
- [ ] `packages/platform/profiling/src/Provider/ProfilingServiceProvider.php`
- [ ] `packages/platform/profiling/src/Provider/ProfilingServiceFactory.php`
- [ ] `packages/platform/profiling/config/profiling.php`
- [ ] `packages/platform/profiling/config/rules.php`
- [ ] `packages/platform/profiling/README.md`
- [ ] `packages/platform/profiling/src/Profiling/ProfilerManager.php`
- [ ] `packages/platform/profiling/src/Profiling/UowProfilerHook.php`
- [ ] `packages/platform/profiling/src/Http/Middleware/ProfileTriggerMiddleware.php`
- [ ] `packages/platform/profiling/src/Driver/NullProfilerDriver.php`
- [ ] `packages/platform/profiling/src/Driver/XhprofProfilerDriver.php`
- [ ] `packages/platform/profiling/src/Driver/BlackfireBridgeDriver.php`
- [ ] `packages/platform/profiling/src/Artifacts/ProfileSummaryWriter.php`
- [ ] `packages/platform/profiling/src/Console/ProfileExplainCommand.php`
- [ ] `packages/platform/profiling/src/Console/ProfilePruneCommand.php`
- [ ] `packages/platform/profiling/src/Exception/ProfilingException.php`
- [ ] `packages/platform/profiling/src/Profiling/Sampling/SamplingPolicy.php` — validated integer-only sampling configuration.
- [ ] `packages/platform/profiling/src/Profiling/Sampling/DeterministicSampler.php` — deterministic sampling decisions without floating-point arithmetic.
- [ ] `packages/platform/profiling/src/Export/NullProfileExporter.php` — safe no-op implementation of the existing exporter port.

Docs:
- [ ] `docs/architecture/profiling.md`
- [ ] `docs/ops/profiling.md` — package usage + drivers + safety policy
- [ ] `docs/ops/runtime-performance-tuning.md` — FPM/RoadRunner/Swoole tuning recommendations

Tests:
- [ ] `packages/platform/profiling/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `packages/platform/profiling/tests/Unit/SamplingPerMilleDeterministicTest.php`
- [ ] `packages/platform/profiling/tests/Integration/HooksStartAndStopExactlyOncePerUowTest.php`
- [ ] `packages/platform/profiling/tests/Integration/ProfilerDoesNotLeakPayloadToLogsTest.php`
- [ ] `tools/tests/Integration/E2E/ProfilingRunsInHttpAndQueueUowTest.php` (optional system-level)
- [ ] `packages/platform/profiling/tests/Contract/ProfileSummaryDoesNotLeakTest.php`
- [ ] `packages/platform/profiling/tests/Integration/UowProfilingHooksDeterministicTest.php`
- [ ] `packages/platform/profiling/tests/Integration/HttpProfileTriggerMiddlewarePolicyTest.php`
- [ ] `packages/platform/profiling/tests/Integration/ProfileExplainCommandSafeOutputTest.php`

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add root row for `profiling` (owner `platform/profiling`, defaults `.../config/profiling.php`, rules `.../config/rules.php`)
- [ ] `docs/ssot/http-middleware-catalog.md` — add profiling trigger middleware entry
- [ ] `docs/ssot/artifacts.md` — register `profile_summary@1`

#### Package skeleton (if type=package)

- [ ] `packages/platform/profiling/composer.json`
- [ ] `packages/platform/profiling/src/Module/ProfilingModule.php`
- [ ] `packages/platform/profiling/src/Provider/ProfilingServiceProvider.php`
- [ ] `packages/platform/profiling/config/profiling.php`
- [ ] `packages/platform/profiling/config/rules.php`
- [ ] `packages/platform/profiling/README.md`
- [ ] `packages/platform/profiling/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/profiling/config/profiling.php`
- [ ] Keys (dot):
  - [ ] `profiling.enabled` = false
  - [ ] `profiling.sampling.per_mille` = 0
  - [ ] `profiling.sampling.min_duration_ms` = 0
  - [ ] `profiling.driver` = `'null'|'xhprof'|'blackfire'`
  - [ ] `profiling.http.enabled` = false
  - [ ] `profiling.http.trigger.mode` = `'off'|'header'|'sampled'`
  - [ ] `profiling.http.trigger.header` = `'X-Coretsia-Profile'`
  - [ ] `profiling.cli.enabled` = true
  - [ ] `profiling.output.dir` = `'packages/applications/skeleton/var/profiling/<appId>'`
  - [ ] `profiling.output.max_summaries` = 100
  - [ ] `profiling.exporter` = "null"                     # "null"|<name from integrations>
  - [ ] `profiling.storage.path` = "packages/applications/skeleton/var/tmp/profiles"
  - [ ] `profiling.redaction.enabled` = true
- [ ] Rules:
  - [ ] raw vendor payloads are optional debug outputs only
  - [ ] normalized summary JSON is the only Coretsia-owned deterministic output

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `packages/core/foundation/src/Tag/ReservedTags.php` (constants)
  - [ ] constants:
    - [ ] `PROFILING_EXPORTER = "profiling.exporter"`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Profiling\Profiling\UowProfilerHook::class`
  - [ ] registers: `\Coretsia\Profiling\Kernel\ProfilingBeforeUowHook::class`
  - [ ] adds tag: `kernel.hook.before_uow` priority `50` meta `{"reason":"start profiling for UoW"}`
  - [ ] registers: `\Coretsia\Profiling\Kernel\ProfilingAfterUowHook::class`
  - [ ] adds tag: `kernel.hook.after_uow` priority `50` meta `{"reason":"stop profiling and export artifact"}`
  - [ ] registers: `\Coretsia\Profiling\Http\Middleware\ProfileTriggerMiddleware::class`
  - [ ] adds tag: `http.middleware.system_pre` priority `520` meta `per owner schema`
  - [ ] registers: `\Coretsia\Profiling\Console\ProfileExplainCommand::class`
  - [ ] binds: `ProfileExporterInterface` → `NullProfileExporter` by default
  - [ ] (optional) selects exporter by `profiling.exporter` via tag `profiling.exporter` meta `{"name":"..."}`
  - [ ] adds tag: `cli.command` priority `0` meta `per owner schema`

- Kernel hooks integration (cemented)
  - Hook services MUST be tagged using string literals:
    - `'kernel.hook.before_uow'`
    - `'kernel.hook.after_uow'`
  - Ordering MUST be exactly TagRegistry order; `platform/profiling` MUST NOT re-sort.
  - Start/stop exactly once per UoW is enforced by:
    - start in before_uow hook
    - stop+export in after_uow hook
    - hook implementations MUST be stateless; if stateful buffers are introduced → implement `ResetInterface` + tag `kernel.reset`.

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `packages/applications/skeleton/var/profiling/<appId>/summary-*.json` (artifact `profile_summary@1`, deterministic keys, LF-only, final newline)
- Reads:
  - N/A

### Runtime outputs (non-artifact; MUST)

- Profiling output files (if enabled) are **NOT** kernel artifacts and MUST NOT use artifact envelope rules.
- Output path MUST be config-driven:
  - `profiling.output.dir` (default: `"var/tmp/profiles"`)
- Output naming MUST be deterministic per generated `profileId` (no timestamps in filenames).
- MUST NOT be included into fingerprint/cache verification inputs.
- MUST NOT log profile payload; only `profileId`, `hash/len` may appear in logs.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::REQUEST_ID` (if present; safe id only)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] active profiler sessions/buffers MUST be reset-safe
  - [ ] stateful services implement `ResetInterface`
  - [ ] tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `profiling.session`
- [ ] Metrics:
  - [ ] `profiling.session_total` (labels: `driver,outcome`)
  - [ ] `profiling.session_duration_ms` (labels: `driver,outcome`)
  - [ ] `profiling.sampled_total` (labels: `operation`, `outcome`)
  - [ ] `profiling.export_total` (labels: `outcome`)
  - [ ] `profiling.duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `uow_type→operation` (values: `http|cli|queue|scheduler`)
- [ ] Logs:
  - [ ] summary only: driver, trigger source, duration, summary path
  - [ ] no raw headers/query/body/cookies/session ids/tokens/raw SQL

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Profiling\Exception\ProfilingException` — errorCodes:
    - [ ] `CORETSIA_PROFILING_FAILED`
    - [ ] `CORETSIA_PROFILING_EXPORT_FAILED`
- [ ] Mapping:
  - [ ] reuse existing error pipeline; no profiling-specific HTTP coupling required

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw request headers/bodies
  - [ ] auth/cookies/session ids/tokens
  - [ ] raw SQL
  - [ ] raw profiler vendor payloads through logs/errors
- [ ] Allowed:
  - [ ] safe ids
  - [ ] `hash(value)` / `len(value)`
  - [ ] normalized summary metrics

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If `kernel.reset` used → `tests/Contract/ProfilingResetWiringTest.php` (asserts: tagged services implement `ResetInterface`; repeated UoW is clean)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php` (asserts: names + label allowlist + no PII)
- [ ] If redaction exists → `tests/Contract/ProfileSummaryDoesNotLeakTest.php` (asserts: raw headers/body/sql/tokens never appear)
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `packages/platform/profiling/tests/Integration/ProfilerDoesNotLeakPayloadToLogsTest.php`
- [ ] If redaction exists → covered by:
  - [ ] `packages/platform/profiling/tests/Integration/ProfilerDoesNotLeakPayloadToLogsTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (if used for boot/wiring)
- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/profiling/tests/Unit/ProfileSummaryWriterDeterministicTest.php`
  - [ ] `packages/platform/profiling/tests/Unit/DriverSelectionPolicyTest.php`
  - [ ] `packages/platform/profiling/tests/Unit/SamplingPerMilleDeterministicTest.php`
- Contract:
  - [ ] `packages/platform/profiling/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `packages/platform/profiling/tests/Contract/ProfileSummaryDoesNotLeakTest.php`
  - [ ] `packages/platform/profiling/tests/Contract/ProfilingResetWiringTest.php`
- Integration:
  - [ ] `packages/platform/profiling/tests/Integration/UowProfilingHooksDeterministicTest.php`
  - [ ] `packages/platform/profiling/tests/Integration/HttpProfileTriggerMiddlewarePolicyTest.php`
  - [ ] `packages/platform/profiling/tests/Integration/ProfileExplainCommandSafeOutputTest.php`
  - [ ] `packages/platform/profiling/tests/Integration/HooksStartAndStopExactlyOncePerUowTest.php`
  - [ ] `packages/platform/profiling/tests/Integration/ProfilerDoesNotLeakPayloadToLogsTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] normalized summary outputs are deterministic
  - [ ] raw vendor payloads are explicitly non-canonical/debug-only
- [ ] Docs updated:
  - [ ] `packages/platform/profiling/README.md`
  - [ ] `docs/ops/profiling.md`
  - [ ] `docs/ops/runtime-performance-tuning.md`
- [ ] Runtime tuning guidance covers:
  - [ ] PHP-FPM
  - [ ] RoadRunner
  - [ ] Swoole
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (no vendor deps in platform; no integrations deps)
- [ ] Verification tests present where applicable
- [ ] Determinism: sampling decisions deterministic (ints only); hooks exactly-once per UoW
- [ ] Docs updated:
  - [ ] `packages/platform/profiling/README.md`
  - [ ] `docs/architecture/profiling.md`
- [ ] What problem this epic solves
  - [ ] Дати sampling profiler, який працює для будь-якого UoW (HTTP/CLI/Queue/Scheduler) через kernel hooks
  - [ ] Детермінований sampling policy (ints only: `per_mille`, `min_duration_ms`) + pluggable exporters через integrations (майбутні)
  - [ ] Заборонити leakage: profile payload ніколи не логиться/не йде в metrics labels
- [ ] Non-goals / out of scope
  - [ ] Vendor профайлер SDKs у platform package (лише ports + glue; vendor — integrations)
  - [ ] Будь-який HTTP endpoint для профайлів (не потрібно)
  - [ ] Float sampling або high-cardinality labels (заборонено)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] KernelRuntime executes `kernel.hook.before_uow` + `kernel.hook.after_uow`
  - [ ] exporter implementation may be missing → noop exporter used
- [ ] Discovery / wiring is via tags:
  - [ ] `kernel.hook.before_uow`
  - [ ] `kernel.hook.after_uow`
  - [ ] `kernel.reset` (if any stateful sampler/exporter selection)
  - [ ] `profiling.exporter` (optional tag used for exporter selection)
- [ ] Увімкнений `platform.profiling` стартує/стопає профайлер рівно 1 раз на UoW через hooks, робить це deterministic і ніколи не ллє payload у логи/метрики.
- [ ] When `profiling.sampling.per_mille=1000`, then every UoW produces a ProfileArtifact and exporter is invoked (or noop) without throwing.

---

### PERF.80.0 Advanced Caching & Preloading Strategy (SHOULD) [IMPL+TOOLING]

---
type: package
phase: PERF
track: performance
epic_id: "PERF.80.0"
owner_path: "packages/devtools/preload/"

package_id: "devtools/preload"
composer: "coretsia/devtools-preload"
kind: runtime
module_id: "devtools.preload"

execution_policy: deferred
required_for_product_release: false
activation: explicit
eligible_after:
- "1.320.0"
- "1.330.0"
- "3.130.0"
- "6.240.0"

goal: "Розширити deterministic preload tooling так, щоб preload plan міг будуватися не лише статично, а й artifact-aware, з окремими advisory outputs для OPcache/JIT без втрати determinism і без залежності від недетермінованих production traces."
provides:
- "Artifact-aware preload plan optimization on top of the existing deterministic preload generator"
- "Separate deterministic preload plan JSON and optional OPcache/JIT recommendation output"
- "Explain/recommend commands for preload coverage and runtime tuning"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced:
- "preload_plan@1"
- "opcache_recommendation@1"

adr: none
ssot_refs:
- "docs/ssot/artifacts.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/observability-and-errors.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - 1.320.0 — module/config artifacts exist
  - 1.330.0 — compiled container artifact exists
  - 3.130.0 — routes artifact/runtime exists where route-aware weighting is used
  - 6.240.0 — base deterministic preload generator exists

- Required deliverables (exact paths):
  - `packages/devtools/preload/` — existing preload package root
  - `packages/core/kernel/` — source of module/artifact inputs
  - `docs/ssot/artifacts.md` — artifact registry to extend
  - `docs/ops/preload.md` — existing preload ops doc to extend

- Required config roots/keys:
  - `preload.enabled`
  - `preload.output.path`
  - `preload.plan.exclude_paths`

- Required tags:
  - `cli.command` — used, not owned, for explain/recommend commands

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/cli`
- `platform/http`
- `integrations/*`
- direct reliance on live production traces as canonical preload input

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - `preload:dump` → existing command from 6.240.0
  - `preload:explain` → `devtools/preload` `src/Console/PreloadExplainCommand.php`
  - `opcache:recommend` → `devtools/preload` `src/Console/OpcacheRecommendCommand.php`

- HTTP:
  - N/A

- Kernel hooks/tags:
  - N/A

- Other runtime discovery / integration tags:
  - `cli.command` priority `0` meta `per owner schema`

- Artifacts:
  - reads:
    - `packages/applications/skeleton/var/cache/<appId>/container.php`
    - `packages/applications/skeleton/var/cache/<appId>/config.php`
    - route artifact(s) if available
  - writes:
    - `packages/applications/skeleton/var/cache/<appId>/preload.php`
    - `packages/applications/skeleton/var/cache/<appId>/preload.plan.json`
    - `packages/applications/skeleton/var/cache/<appId>/opcache.recommendation.json`

- Notes (only if applicable):
  - This epic MUST NOT invent any non-deterministic “auto-learn from prod traffic” mode.
  - If external usage evidence is ever imported, it MUST be normalized offline into deterministic input data before planning.

### Deliverables (MUST)

#### Creates

- [ ] `packages/devtools/preload/src/Generator/PreloadPlanOptimizer.php` — artifact-aware candidate weighting
- [ ] `packages/devtools/preload/src/Generator/ArtifactWeightedPreloadSource.php` — deterministic extraction from Coretsia artifacts
- [ ] `packages/devtools/preload/src/Console/PreloadExplainCommand.php` — explain chosen/excluded files
- [ ] `packages/devtools/preload/src/Console/OpcacheRecommendCommand.php` — emit advisory recommendation output
- [ ] `packages/devtools/preload/src/Artifacts/PreloadPlanWriter.php` — writes `preload.plan.json`
- [ ] `packages/devtools/preload/src/Artifacts/OpcacheRecommendationWriter.php` — writes advisory recommendation JSON
- [ ] `packages/devtools/preload/src/Opcache/JitPolicy.php` — deterministic advisory model only
- [ ] `packages/devtools/preload/tests/Unit/PreloadPlanOptimizerDeterministicTest.php`
- [ ] `packages/devtools/preload/tests/Integration/PreloadExplainCommandDeterministicOutputTest.php`
- [ ] `packages/devtools/preload/tests/Integration/OpcacheRecommendCommandDeterministicOutputTest.php`
- [ ] `docs/ops/preload-optimization.md` — artifact-aware preload and OPcache/JIT guidance

#### Modifies

- [ ] `packages/devtools/preload/config/preload.php` — extend config subtree
- [ ] `packages/devtools/preload/config/rules.php` — enforce new shape
- [ ] `packages/devtools/preload/README.md` — document explain/recommend flow
- [ ] `docs/ssot/artifacts.md` — register:
  - [ ] `preload_plan@1`
  - [ ] `opcache_recommendation@1`
- [ ] `docs/ops/preload.md` — link advanced strategy
- [ ] `packages/devtools/preload/src/Generator/PreloadPlanBuilder.php` — allow optimizer integration without losing deterministic ordering

#### Package skeleton (if type=package)

- [ ] `packages/devtools/preload/composer.json`
- [ ] `packages/devtools/preload/src/Module/PreloadModule.php`
- [ ] `packages/devtools/preload/src/Provider/PreloadServiceProvider.php`
- [ ] `packages/devtools/preload/config/preload.php`
- [ ] `packages/devtools/preload/config/rules.php`
- [ ] `packages/devtools/preload/README.md`
- [ ] `packages/devtools/preload/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/devtools/preload/config/preload.php`
- [ ] Keys (dot):
  - [ ] `preload.plan.strategy` = `'static'|'artifact_weighted'`
  - [ ] `preload.plan.sources` = `['container','config','routes']`
  - [ ] `preload.plan.write_json` = true
  - [ ] `preload.opcache.recommend.enabled` = false
  - [ ] `preload.opcache.recommend.jit` = `'off'|'tracing'`
  - [ ] `preload.opcache.recommend.emit_ini` = false
- [ ] Rules:
  - [ ] recommendation output is advisory only and MUST NOT mutate runtime environment automatically
  - [ ] planning inputs MUST be deterministic and file-backed

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Devtools\Preload\Console\PreloadExplainCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"preload:explain"}`
  - [ ] registers: `\Coretsia\Devtools\Preload\Console\OpcacheRecommendCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"opcache:recommend"}`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `packages/applications/skeleton/var/cache/<appId>/preload.plan.json` (artifact `preload_plan@1`, deterministic bytes)
  - [ ] `packages/applications/skeleton/var/cache/<appId>/opcache.recommendation.json` (artifact `opcache_recommendation@1`, deterministic bytes)
- [ ] Reads:
  - [ ] validates source artifact headers/payloads before planning

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional logs only)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] caches/planners with mutable state implement `ResetInterface`
  - [ ] stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `preload.optimize`
  - [ ] `opcache.recommend`
- [ ] Metrics:
  - [ ] `preload.optimize_total` (labels: `outcome`)
  - [ ] `preload.optimize_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] summary only: candidate counts, included/excluded counts, output paths
  - [ ] no file contents, no raw source payloads

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Devtools\Preload\Exception\PreloadPlanSourceException` — errorCode `CORETSIA_PRELOAD_PLAN_SOURCE_INVALID`
  - [ ] `Coretsia\Devtools\Preload\Exception\OpcacheRecommendationException` — errorCode `CORETSIA_OPCACHE_RECOMMENDATION_WRITE_FAILED`
- [ ] Mapping:
  - [ ] deterministic CLI error output only

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] absolute paths outside configured output roots
  - [ ] artifact payload contents
  - [ ] secrets from config/env artifacts
- [ ] Allowed:
  - [ ] normalized relative paths
  - [ ] counts
  - [ ] safe recommendation flags

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If `kernel.reset` used → `packages/devtools/preload/tests/Contract/PreloadPlannerResetSafetyTest.php`
- [ ] If metrics/spans/logs exist → `packages/devtools/preload/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → `packages/devtools/preload/tests/Integration/PreloadExplainCommandDeterministicOutputTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `packages/core/kernel/tests/Fixtures/MicroApp/`
- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `packages/devtools/preload/tests/Unit/PreloadPlanOptimizerDeterministicTest.php`
  - [ ] `packages/devtools/preload/tests/Unit/JitPolicyAdvisoryOnlyTest.php`
- Contract:
  - [ ] `packages/devtools/preload/tests/Contract/PreloadPlanIsDeterministicContractTest.php`
  - [ ] `packages/devtools/preload/tests/Contract/PreloadPlannerResetSafetyTest.php`
- Integration:
  - [ ] `packages/devtools/preload/tests/Integration/PreloadExplainCommandDeterministicOutputTest.php`
  - [ ] `packages/devtools/preload/tests/Integration/OpcacheRecommendCommandDeterministicOutputTest.php`
  - [ ] `packages/devtools/preload/tests/Integration/RerunNoDiffForPreloadPlanArtifactTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] preload plan remains rerun-no-diff
  - [ ] recommendation artifacts remain deterministic
  - [ ] no live nondeterministic traces become canonical inputs
- [ ] Docs updated:
  - [ ] `packages/devtools/preload/README.md`
  - [ ] `docs/ops/preload.md`
  - [ ] `docs/ops/preload-optimization.md`
