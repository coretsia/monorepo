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

## PHASE 2 — Mode Infrastructure & CLI (Non-product doc)

### 2.10.0 Mode preset PHP source schema + packaging policy (MUST) [IMPL]

---
type: code
phase: 2
epic_id: "2.10.0"
owner_path: "framework/packages/core/kernel/"

goal: "Зацементувати й реалізаційно посилити canonical Kernel-owned PHP mode preset source contract, deterministic source-to-loaded-state round trip та packaging ownership."
provides:
- "Strict canonical PHP mode preset source payload enforced by ModePresetSchemaValidator"
- "Exact source-to-export round-trip invariant for every accepted preset payload"
- "Fail-fast rejection of non-canonical module-id sets and non-canonical map ordering"
- "Supported in-process isolation for ordinary buffered PHP output and callback-handled PHP diagnostics"
- "Clear separation between source payload, immutable ModePreset state, moduleIds() accessor projection, and toArray() export"
- "Packaging policy for framework-owned canonical presets and user-owned skeleton overrides"
- "Aligned contracts SSoT, Kernel ADR, code, tests, resources, and packaging law"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none

ssot_refs:
- "docs/ssot/modes.md"
- "docs/ssot/mode-preset-sources.md"
- "docs/architecture/PACKAGING.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Existing documentation updated by this epic:
  - `docs/ssot/INDEX.md`
  - `docs/ssot/modes.md`
  - `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
  - `docs/architecture/PACKAGING.md`

- Existing implementation hardened by this epic:
  - `framework/packages/core/kernel/src/Module/ModePreset.php`
  - `framework/packages/core/kernel/src/Module/ModePresetSchemaValidator.php`
  - `framework/packages/core/kernel/src/Module/FilesystemModePresetLoader.php`
  - `framework/packages/core/kernel/src/Module/Exception/ModePresetInvalidException.php`
  - `framework/packages/core/kernel/resources/modes/hybrid.php`
  - `framework/packages/core/kernel/resources/modes/enterprise.php`

- Existing contracts and implementation surfaces retained:
  - `framework/packages/core/contracts/src/Module/ModePresetInterface.php`
  - `framework/packages/core/contracts/src/Module/ModuleId.php`
  - `framework/packages/core/kernel/src/Module/ModePresetLoaderFactory.php`
  - `framework/packages/core/kernel/src/Module/Exception/ModePresetNotFoundException.php`
  - `framework/packages/core/kernel/config/kernel.php`
  - `framework/packages/core/kernel/resources/modes/micro.php`
  - `framework/packages/core/kernel/resources/modes/express.php`

- Existing packaging enforcement retained:
  - `framework/tools/gates/no_skeleton_mode_presets_default_gate.php`

- Scope constraints:
  - no new public runtime entrypoint is introduced
  - no new package dependency edge is introduced
  - no new config root or config key is introduced
  - no new ErrorCode is introduced
  - no new Composer command or gate is introduced
  - stable validation reason tokens MAY be added under the existing `CORETSIA_MODE_PRESET_INVALID` ErrorCode
  - implementation, tests, canonical preset resources, and documentation MAY be modified where required to enforce the accepted source contract

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts` — existing `core/kernel` dependency only

Forbidden:
- new package dependency edges introduced by this epic
- `platform/*`
- `integrations/*`
- framework tooling packages

### Entry points / integration points (MUST)

- No new public runtime entrypoint is introduced.

- Existing runtime integration path:
  - retained:
    - `ModePresetLoaderFactory::createFor(BootstrapConfig)`
  - hardened:
    - `FilesystemModePresetLoader::load(string)`
    - `FilesystemModePresetLoader::tryLoad(string)`
    - `ModePresetSchemaValidator::validate(string, mixed)`
    - `ModePreset::__construct(...)`
    - `ModePreset::toArray()`

- Public signatures remain unchanged:
  - `ModePresetInterface`
  - `ModePresetLoaderInterface`
  - `ModuleId`

### Deliverables (MUST)

#### Creates

- [ ] `docs/ssot/mode-preset-sources.md`
  - [ ] document metadata:
    - [ ] `ssotVersion: 1`
    - [ ] `status: pre-stable`
    - [ ] `owner: core/kernel`
  - [ ] scope:
    - [ ] defines and documents the canonical Kernel PHP mode-preset source contract
    - [ ] hardens the existing implementation where it currently accepts non-canonical source representations
    - [ ] does not redefine the format-neutral `ModePresetInterface` accessor surface
    - [ ] does not introduce an alternative loader, validator, source path, precedence rule, config input, or ErrorCode
    - [ ] `ModePresetSchemaValidator` is the executable owner of raw payload validation and canonical source enforcement
    - [ ] `ModePreset` is the executable owner of immutable semantic loaded-state invariants
    - [ ] `FilesystemModePresetLoader` is the executable owner of PHP file lookup, output isolation, and loading
    - [ ] `ModePresetLoaderFactory` is the executable owner of config-relative source-directory resolution
    - [ ] accepted source payloads MUST satisfy the exact round-trip invariant:
      - [ ] `ModePresetSchemaValidator::validate($name, $payload)->toArray() === $payload`
    - [ ] semantic validation failures take precedence over canonical-representation failures
  - [ ] implementation references:
    - [ ] `Coretsia\Contracts\Module\ModePresetInterface`
    - [ ] `Coretsia\Contracts\Module\ModuleId`
    - [ ] `Coretsia\Kernel\Module\ModePreset`
    - [ ] `Coretsia\Kernel\Module\ModePresetSchemaValidator`
    - [ ] `Coretsia\Kernel\Module\FilesystemModePresetLoader`
    - [ ] `Coretsia\Kernel\Module\ModePresetLoaderFactory`
    - [ ] `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
  - [ ] source-directory resolution:
    - [ ] runtime source directories are config-derived rather than hardcoded inside the loader
    - [ ] framework defaults directory resolves as:
      - [ ] `<core/kernel-package-root>/<kernel.modes.defaults_path>`
    - [ ] skeleton overrides directory resolves as:
      - [ ] `<BootstrapConfig::skeletonRoot()>/<kernel.modes.overrides_path>`
    - [ ] current canonical config defaults are:
      - [ ] `kernel.modes.schema_version = 1`
      - [ ] `kernel.modes.defaults_path = resources/modes`
      - [ ] `kernel.modes.overrides_path = config/modes`
    - [ ] current shipped framework location is therefore:
      - [ ] `framework/packages/core/kernel/resources/modes/*.php`
    - [ ] current default skeleton override location is therefore:
      - [ ] `skeleton/config/modes/*.php`
  - [ ] lookup and precedence:
    - [ ] requested preset name is supplied by `BootstrapConfig::preset()`
    - [ ] skeleton override file is checked first
    - [ ] framework default file is checked second
    - [ ] first existing file wins
    - [ ] skeleton and framework payloads are never merged
    - [ ] missing skeleton override is not an error
    - [ ] missing both sources maps to `CORETSIA_MODE_PRESET_NOT_FOUND`
    - [ ] present but unreadable, unexecutable, or invalid source maps to `CORETSIA_MODE_PRESET_INVALID`
  - [ ] PHP file loading contract:
    - [ ] candidate filename is exactly `<requested-preset-name>.php`
    - [ ] only direct `.php` files under the resolved source directory participate
    - [ ] nested directories are not recursively discovered
    - [ ] the selected file is executed through PHP `require` inside a static local closure
    - [ ] the return value is passed to `ModePresetSchemaValidator`
    - [ ] the returned value MUST be an associative array
    - [ ] supported in-process source execution isolation:
      - [ ] immediately before `require`, the loader records the current output-buffer level
      - [ ] the loader starts exactly one loader-owned output buffer
      - [ ] failure to start the output buffer maps to generic invalid-source failure
      - [ ] the loader installs a temporary error handler for `E_ALL`
      - [ ] the return value of `set_error_handler()` is retained as the previous handler
      - [ ] callback-handled warnings, notices, and deprecations are converted into a caught `ErrorException`
      - [ ] the temporary handler does not delegate source diagnostics to the previously installed handler
    - [ ] ordinary PHP output behavior:
      - [ ] output routed through the loader-owned PHP output buffer is captured
      - [ ] captured bytes are never forwarded to the caller or an outer output buffer
      - [ ] captured bytes are discarded and never included in diagnostics
      - [ ] when `require` otherwise completes successfully, any non-empty captured output maps to:
        - [ ] `ModePresetInvalidException::REASON_SOURCE_OUTPUT_FORBIDDEN`
    - [ ] cleanup for supported sources:
      - [ ] the loader closes only the output buffer it created
      - [ ] output buffers that existed before loading MUST NOT be closed
      - [ ] the previous error handler is restored in `finally`
      - [ ] restoration uses `restore_error_handler()` exactly once for the loader-owned handler
      - [ ] normal success and normal failure restore the original output-buffer level
      - [ ] normal success and normal failure restore the previous error handler
    - [ ] unsupported source behavior:
      - [ ] a preset source MUST NOT call output-buffer management functions
      - [ ] a preset source MUST NOT replace or restore error handlers
      - [ ] a preset source MUST NOT write directly to `STDOUT`, `STDERR`, or equivalent direct streams
      - [ ] a preset source MUST NOT rely on:
        - [ ] mutation of global state
        - [ ] environment-dependent values
        - [ ] timestamps
        - [ ] randomness
        - [ ] filesystem mutation
        - [ ] network access
        - [ ] process-specific state
      - [ ] deliberate output-buffer, error-handler, or direct-stream manipulation is outside the in-process containment guarantee
      - [ ] the loader does not claim to sandbox arbitrary PHP execution
      - [ ] detected loader-owned state corruption maps to generic invalid-source failure
      - [ ] cleanup of additional source-created output buffers is best-effort
      - [ ] the loader MUST NOT claim that caller-owned buffers destroyed by arbitrary source code can be reconstructed
  - [ ] existing-source failure precedence:
    1. [ ] unreadable source file:
      - [ ] `CORETSIA_MODE_PRESET_INVALID`
      - [ ] `mode-preset-invalid`
    2. [ ] source execution Throwable, callback-handled PHP diagnostic, or loader-owned isolation setup/cleanup failure:
      - [ ] `CORETSIA_MODE_PRESET_INVALID`
      - [ ] `mode-preset-invalid`
    3. [ ] successful source execution with non-empty captured ordinary PHP output:
      - [ ] `CORETSIA_MODE_PRESET_INVALID`
      - [ ] `mode-preset-source-output-forbidden`
    4. [ ] structural, type, schema, safety, or set-overlap validation:
      - [ ] retains the existing most-specific semantic reason
    5. [ ] semantically valid but non-canonical payload:
      - [ ] `CORETSIA_MODE_PRESET_INVALID`
      - [ ] `mode-preset-source-not-canonical`
  - [ ] failure-precedence implications:
    - [ ] a source execution Throwable takes precedence over output captured before that Throwable
    - [ ] source output takes precedence over payload validation because an output-producing source is not an accepted payload producer
    - [ ] semantic payload validation takes precedence over canonical round-trip comparison
    - [ ] canonical round-trip comparison is performed only after all semantic validation succeeds
  - [ ] exact canonical raw top-level shape:
    - [ ] the payload contains exactly eight keys in this insertion order:
      1. `schemaVersion`
      2. `name`
      3. `description`
      4. `required`
      5. `optional`
      6. `disabled`
      7. `featureBundles`
      8. `metadata`
    - [ ] all eight keys are required
    - [ ] missing keys are rejected
    - [ ] unknown keys are rejected
    - [ ] non-string top-level keys are rejected
    - [ ] reordered top-level keys are rejected as non-canonical source
    - [ ] root wrappers `kernel`, `mode`, and `modes` are rejected
    - [ ] `moduleIds` is forbidden in source payloads
  - [ ] field contract:
    - [ ] `schemaVersion`:
      - [ ] exact integer `ModePresetInterface::SCHEMA_VERSION`
      - [ ] currently `1`
    - [ ] `name`:
      - [ ] string
      - [ ] non-empty
      - [ ] maximum `64` bytes
      - [ ] first byte is lowercase ASCII letter
      - [ ] remaining bytes use lowercase ASCII letters, digits, or hyphen
      - [ ] must equal the requested preset name byte-for-byte
    - [ ] `description`:
      - [ ] `null` or non-empty string
      - [ ] maximum `512` bytes
      - [ ] no C0/DEL control bytes
      - [ ] no path-like value
    - [ ] `required`, `optional`, and `disabled`:
      - [ ] each value is a PHP list
      - [ ] every item is a string accepted by `ModuleId::fromString()`
    - [ ] `featureBundles` and `metadata`:
      - [ ] each value is a JSON-like map
      - [ ] empty array is accepted as the empty map representation
  - [ ] canonical source module-id contract:
    - [ ] `required`, `optional`, and `disabled` are set-shaped PHP lists
    - [ ] source list order is not domain-semantic, but canonical source representation is normative
    - [ ] every item is parsed through `ModuleId::fromString()`
    - [ ] every raw source string MUST equal `ModuleId::value()` byte-for-byte
    - [ ] source values that require ASCII case normalization are rejected as non-canonical
    - [ ] duplicate module ids within one source list are rejected
    - [ ] duplicate identity is evaluated using canonical `ModuleId::value()`
    - [ ] every source list MUST already be sorted ascending using byte-order `strcmp`
    - [ ] unsorted source lists are rejected
    - [ ] `required`, `optional`, and `disabled` MUST be pairwise disjoint
    - [ ] cross-list overlap is checked using canonical module-id identity
    - [ ] overlap is a semantic invalid-preset failure and takes precedence over generic non-canonical-source failure
  - [ ] loaded-state module-id contract:
    - [ ] `ModePreset` stores canonical `ModuleId` objects
    - [ ] `ModePreset` MAY sort direct-construction input because set order is not semantic
    - [ ] `ModePreset` MUST reject duplicate `ModuleId` values supplied through direct construction
    - [ ] `ModePreset` MUST reject pairwise overlap between `required`, `optional`, and `disabled`
    - [ ] `ModePreset` MUST NOT silently collapse duplicate direct-construction values
  - [ ] JSON-like normalization contract:
    - [ ] allowed scalar values are:
      - [ ] `null`
      - [ ] `bool`
      - [ ] `int`
      - [ ] `string`
    - [ ] nested lists preserve list order
    - [ ] nested list order remains semantic source data and is not sorted
    - [ ] nested map keys are sorted recursively using byte-order `strcmp`
    - [ ] raw source map keys MUST already be recursively sorted using byte-order `strcmp`
    - [ ] a semantically valid map that would change key order during normalization is rejected as non-canonical source
    - [ ] maximum recursive depth is `16`
    - [ ] recursive depth counts array containers, not scalar leaves
    - [ ] the top-level `featureBundles` or `metadata` map is container depth `0`
    - [ ] every nested list or map increments container depth by `1`
    - [ ] an array container at depth greater than `16` is rejected
    - [ ] a scalar value does not create an additional container depth
    - [ ] every individual list or map container may contain at most `256` entries
    - [ ] the `256`-entry limit applies equally to lists and maps
    - [ ] maximum string length is `1024` bytes
    - [ ] control characters in strings and map keys are rejected
    - [ ] path-like strings and map keys are rejected
    - [ ] floats, objects, closures, resources, streams, services, and filesystem handles are rejected
  - [ ] loaded-state and export distinction:
    - [ ] `ModePreset::required()`, `optional()`, and `disabled()` return canonical `list<ModuleId>`
    - [ ] `ModePreset::moduleIds()` is a derived compatibility accessor projection
    - [ ] `moduleIds()` is not independently stored source state
    - [ ] `moduleIds` is forbidden in source payloads
    - [ ] `moduleIds` is not emitted by `ModePreset::toArray()`
    - [ ] excluding `moduleIds` prevents redundant exported state and preserves one source of truth
    - [ ] `ModePreset::toArray()` emits exactly these eight fields in this order:
      1. `schemaVersion`
      2. `name`
      3. `description`
      4. `required`
      5. `optional`
      6. `disabled`
      7. `featureBundles`
      8. `metadata`
    - [ ] module-id fields in `toArray()` are canonical strings
    - [ ] map keys in `featureBundles` and `metadata` are recursively `strcmp`-sorted
    - [ ] nested list order is preserved
    - [ ] `toArray()` is the canonical scalar representation of source-owned preset state
    - [ ] every accepted source payload MUST satisfy:
      - [ ] `$preset = $validator->validate($requestedName, $payload)`
      - [ ] `$preset->toArray() === $payload`
    - [ ] a semantically valid payload that fails this strict equality is rejected with `mode-preset-source-not-canonical`
  - [ ] diagnostics and redaction:
    - [ ] resolved filesystem paths are not exposed
    - [ ] raw source payloads are not exposed
    - [ ] raw PHP warning or Throwable details MUST NOT appear in deterministic diagnostic surfaces:
      - [ ] `errorCode()`
      - [ ] `reason()`
      - [ ] `getMessage()`
      - [ ] `context()`
      - [ ] logs
      - [ ] metric labels
      - [ ] span attributes
      - [ ] exported diagnostic payloads
    - [ ] the original source Throwable MAY remain attached as `getPrevious()` according to the existing module-resolution exception chaining policy
    - [ ] the previous Throwable chain MUST NOT be copied or serialized into deterministic diagnostics or observability
    - [ ] `ModePresetInvalidException` adds:
      - [ ] `REASON_SOURCE_NOT_CANONICAL = mode-preset-source-not-canonical`
      - [ ] `REASON_SOURCE_OUTPUT_FORBIDDEN = mode-preset-source-output-forbidden`
    - [ ] both reasons remain under the existing:
      - [ ] `CORETSIA_MODE_PRESET_INVALID`
    - [ ] no new ErrorCode is introduced
    - [ ] secrets, environment values, stack traces, and source contents are not exposed

#### Modifies

- [ ] `framework/packages/core/kernel/src/Module/ModePresetSchemaValidator.php`
  - [ ] retain existing structural, type, safety, and pairwise-disjointness validation
  - [ ] construct the normalized immutable `ModePreset`
  - [ ] before returning, compare:
    - [ ] `$preset->toArray()`
    - [ ] original raw `$payload`
  - [ ] comparison uses strict PHP array identity semantics:
    - [ ] value
    - [ ] type
    - [ ] list order
    - [ ] map key insertion order
  - [ ] mismatch throws:
    - [ ] `ModePresetInvalidException::REASON_SOURCE_NOT_CANONICAL`
  - [ ] semantic validation executes before canonical round-trip comparison
  - [ ] validator MUST NOT silently accept source casing, duplicate sets, set reordering, or map-key reordering

- [ ] `framework/packages/core/kernel/src/Module/ModePreset.php`
  - [ ] retain canonical sorting of direct-construction module-id sets
  - [ ] change `normalizeModuleIdSet()` so duplicate canonical `ModuleId::value()` entries are rejected
  - [ ] duplicate rejection uses stable field-specific internal reason:
    - [ ] `mode-preset-<field>-module-id-duplicate`
  - [ ] retain pairwise-disjointness checks
  - [ ] retain recursive JSON-like normalization
  - [ ] retain the exact eight-field `toArray()` export
  - [ ] do not add `moduleIds` to `toArray()`

- [ ] `framework/packages/core/kernel/src/Module/Exception/ModePresetInvalidException.php`
  - [ ] add:
    - [ ] `REASON_SOURCE_NOT_CANONICAL`
    - [ ] `REASON_SOURCE_OUTPUT_FORBIDDEN`
  - [ ] add both reasons to the internal reason allowlist
  - [ ] retain `CORETSIA_MODE_PRESET_INVALID`
  - [ ] do not add or rename an ErrorCode
  - [ ] diagnostics remain limited to safe preset name and reason token

- [ ] `framework/packages/core/kernel/src/Module/FilesystemModePresetLoader.php`
  - [ ] retain override-first/default-second lookup
  - [ ] retain first-existing-file-wins behavior
  - [ ] retain no-merge behavior
  - [ ] execute the selected source under a loader-owned output buffer
  - [ ] install and restore a temporary PHP error handler around `require`
  - [ ] restore output/error-handler state through `finally`
  - [ ] reject non-empty source output with `REASON_SOURCE_OUTPUT_FORBIDDEN`
  - [ ] prevent ordinary PHP output routed through the loader-owned buffer and callback-handled PHP diagnostics from escaping under supported source behavior
  - [ ] retain generic invalid-source mapping for thrown source exceptions
  - [ ] enforce exact existing-source failure precedence:
    - [ ] execution failure before captured-output failure
    - [ ] captured-output failure before payload validation
    - [ ] semantic validation before canonicality validation
  - [ ] source Throwable MAY be retained only as the exception `previous`
  - [ ] raw source Throwable data MUST NOT be copied into message, reason, context, logs, metrics, spans, or exported diagnostics
  - [ ] do not expose resolved paths or raw source content

- [ ] `framework/packages/core/kernel/resources/modes/hybrid.php`
  - [ ] reorder `optional` to exact byte-order `strcmp` order:
    1. `platform.http`
    2. `platform.logging`
    3. `platform.metrics`
    4. `platform.tracing`
    5. `platform.worker`
  - [ ] no semantic module membership change

- [ ] `framework/packages/core/kernel/resources/modes/enterprise.php`
  - [ ] reorder `optional` to exact byte-order `strcmp` order:
    1. `platform.http`
    2. `platform.logging`
    3. `platform.metrics`
    4. `platform.tracing`
    5. `platform.worker`
  - [ ] no semantic module membership change

- [ ] `framework/packages/core/kernel/tests/Contract/ModePresetConstructorPolicyContractTest.php`
  - [ ] add direct-construction duplicate rejection coverage for:
    - [ ] duplicate `required`
    - [ ] duplicate `optional`
    - [ ] duplicate `disabled`
  - [ ] retain acceptance of unsorted but otherwise valid direct-construction sets
  - [ ] assert returned loaded sets are `strcmp`-sorted

- [ ] `framework/packages/core/kernel/tests/Contract/ModePresetExportShapeContractTest.php`
  - [ ] retain exact eight-field export order
  - [ ] explicitly assert `moduleIds` key is absent
  - [ ] retain separate `moduleIds()` accessor coverage

- [ ] `framework/packages/core/kernel/tests/Contract/ModuleResolutionExceptionsExposeSafeDiagnosticsContractTest.php`
  - [ ] include both new reasons in ModePreset invalid-exception coverage:
    - [ ] `mode-preset-source-not-canonical`
    - [ ] `mode-preset-source-output-forbidden`
  - [ ] use a previous Throwable containing:
    - [ ] an absolute path
    - [ ] a token-like value
    - [ ] a newline
  - [ ] assert raw previous data is absent from:
    - [ ] error code
    - [ ] reason
    - [ ] message
    - [ ] context
  - [ ] retain the existing exception-chaining policy

- [ ] `docs/ssot/INDEX.md`
  - [ ] register `docs/ssot/mode-preset-sources.md` exactly once
  - [ ] add the entry under `Shapes and Contracts`
  - [ ] use the exact entry: - [Mode Preset Sources SSoT](./mode-preset-sources.md) — owner: core/kernel — ssotVersion: 1 — scope: kernel,mode-preset,php,source,validation
  - [ ] place the entry immediately before `./modes.md`
  - [ ] preserve byte-order `strcmp` ordering by relative path
  - [ ] index `ssotVersion` MUST equal the document `ssotVersion`

- [ ] `docs/ssot/modes.md`
  - [ ] remains owned by `core/contracts`
  - [ ] remains source-format neutral at the contracts boundary
  - [ ] does not duplicate the exact PHP source payload schema
  - [ ] update the document scope so it owns:
    - [ ] canonical mode vocabulary
    - [ ] `ModePresetInterface` semantics
    - [ ] format-neutral loader-port semantics
    - [ ] loaded-preset invariants
  - [ ] replace absolute source-validator/direct-constructor parity wording:
    - [ ] `ModePreset` direct construction enforces semantic loaded-state safety
    - [ ] source-only canonical representation is owned by `ModePresetSchemaValidator`
    - [ ] source-only rules include:
      - [ ] raw top-level key insertion order
      - [ ] canonical raw module-id spelling
      - [ ] canonical source set order
      - [ ] canonical recursive map-key order
    - [ ] direct construction does not receive raw source strings or raw source map ordering
    - [ ] direct construction MAY canonicalize non-semantic ordering
    - [ ] direct construction MUST reject:
      - [ ] duplicate module-id identities
      - [ ] pairwise set overlap
      - [ ] unsafe name or description
      - [ ] unsafe JSON-like values
  - [ ] remove or replace stale future-tense statements:
    - [ ] remove `A concrete loader implementation belongs to a future owner package.`
    - [ ] replace it with a statement that the current Kernel-owned implementation is documented by:
      - [ ] `docs/ssot/mode-preset-sources.md`
      - [ ] `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
  - [ ] correct `ModePreset exported shape`:
    - [ ] retain `moduleIds()` as a derived interface accessor
    - [ ] remove `moduleIds` from the documented current `toArray()` field list
    - [ ] document the canonical eight-field `toArray()` shape:
      - [ ] `schemaVersion`
      - [ ] `name`
      - [ ] `description`
      - [ ] `required`
      - [ ] `optional`
      - [ ] `disabled`
      - [ ] `featureBundles`
      - [ ] `metadata`
    - [ ] state that the eight-field shape intentionally matches canonical source state
    - [ ] state that accepted Kernel PHP sources round-trip exactly through `toArray()`
    - [ ] state that `moduleIds()` is derived and therefore excluded to avoid duplicate exported state
    - [ ] state that `moduleIds()` is not source state and is not part of the current exported array
  - [ ] replace the concrete-source items in `Non-goals`:
    - [ ] this contracts SSoT does not itself define the concrete PHP source format
    - [ ] this contracts SSoT does not itself own source-directory resolution
    - [ ] the concrete Kernel implementation is normatively documented in `docs/ssot/mode-preset-sources.md`
    - [ ] lookup/resolution architecture remains documented in ADR-0024
  - [ ] retain format neutrality:
    - [ ] `ModePresetInterface` does not expose PHP filenames or source directories
    - [ ] callers continue to depend on the interface rather than `FilesystemModePresetLoader`

- [ ] `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
  - [ ] retain existing implementation ownership and resolution pipeline
  - [ ] retain the existing mode-preset loading section
  - [ ] retain config-derived path resolution:
    - [ ] skeleton override first
    - [ ] framework default second
    - [ ] first existing file wins
    - [ ] no merge
  - [ ] add a normative reference to `docs/ssot/mode-preset-sources.md` for:
    - [ ] exact raw PHP payload key set
    - [ ] field validation
    - [ ] normalization behavior
    - [ ] loaded/exported shape distinction
  - [ ] clarify source validation vs loaded-state construction:
    - [ ] `ModePresetSchemaValidator` owns exact source canonicality
    - [ ] `ModePreset` owns safe semantic loaded state
    - [ ] direct construction is not required to revalidate raw PHP array insertion order
    - [ ] direct construction rejects duplicate and overlapping module identities
    - [ ] direct construction may canonicalize non-semantic ordering
  - [ ] document canonical round-trip:
    - [ ] every accepted source payload equals its `ModePreset::toArray()` result
    - [ ] semantically equivalent but non-canonical source payloads fail deterministically
    - [ ] `moduleIds()` remains derived and is not duplicated in the canonical export
  - [ ] document source execution isolation:
    - [ ] ordinary PHP output routed through the loader-owned buffer is captured, discarded, and rejected
    - [ ] callback-handled PHP warnings, notices, and deprecations do not escape
    - [ ] direct-stream writes and deliberate output-buffer or error-handler manipulation are outside the in-process containment guarantee
    - [ ] arbitrary PHP execution is not represented as fully sandboxed
  - [ ] do not duplicate the complete eight-key schema inside the ADR
  - [ ] do not describe the loader or validator as future work

- [ ] `docs/architecture/PACKAGING.md`
  - [ ] add a normative “Mode preset packaging” section
  - [ ] framework distribution:
    - [ ] `core/kernel` ships the four canonical preset files:
      - [ ] `framework/packages/core/kernel/resources/modes/micro.php`
      - [ ] `framework/packages/core/kernel/resources/modes/express.php`
      - [ ] `framework/packages/core/kernel/resources/modes/hybrid.php`
      - [ ] `framework/packages/core/kernel/resources/modes/enterprise.php`
    - [ ] every shipped framework preset MUST satisfy the canonical source round-trip contract
    - [ ] shipped framework preset module-id sets MUST already be unique and `strcmp`-sorted
    - [ ] shipped framework preset map keys MUST already be recursively `strcmp`-sorted
    - [ ] the shipped package-relative directory matches the current default:
      - [ ] `kernel.modes.defaults_path = resources/modes`
  - [ ] default skeleton distribution:
    - [ ] default skeleton ships no preset PHP file matching:
      - [ ] `skeleton/config/modes/*.php`
    - [ ] this matches the current skeleton-relative default:
      - [ ] `kernel.modes.overrides_path = config/modes`
    - [ ] absence of the directory is valid
    - [ ] an empty directory has no runtime semantic effect
  - [ ] project ownership:
    - [ ] project owners MAY add `skeleton/config/modes/<preset>.php`
    - [ ] those files are user-owned overrides
    - [ ] user-owned overrides use the same strict source schema as framework-owned presets
    - [ ] override status does not weaken canonicality, safety, output, or redaction requirements
    - [ ] an override replaces the framework preset of the same name
    - [ ] override and framework source are never merged
    - [ ] the default-skeleton packaging prohibition does not prohibit post-creation user-owned override files
  - [ ] path semantics:
    - [ ] packaging locations describe currently shipped files
    - [ ] runtime directories are resolved from `kernel.modes.defaults_path` and `kernel.modes.overrides_path`
    - [ ] PACKAGING MUST NOT imply that those path strings are hardcoded in `FilesystemModePresetLoader`
  - [ ] reference:
    - [ ] link exact PHP source and normalization rules to `docs/ssot/mode-preset-sources.md`
    - [ ] link runtime lookup architecture to ADR-0024

### Verification (MUST)

#### Canonical source contract

- [ ] all four framework preset files load successfully
- [ ] all four framework preset payloads satisfy:
  - [ ] `$validator->validate($name, $payload)->toArray() === $payload`
- [ ] exact top-level key order is enforced
- [ ] `moduleIds` is rejected as a source key
- [ ] non-canonical module-id casing is rejected
- [ ] duplicate source module ids are rejected
- [ ] unsorted source module-id lists are rejected
- [ ] unsorted source map keys are rejected
- [ ] unsorted nested map keys are rejected
- [ ] nested list order remains preserved
- [ ] pairwise set overlap retains its dedicated semantic failure precedence

#### Loaded-state contract

- [ ] direct `ModePreset` construction rejects duplicate module identities
- [ ] direct construction rejects pairwise set overlap
- [ ] direct construction may accept unsorted set input
- [ ] loaded module-id sets are emitted in byte-order `strcmp` order
- [ ] `moduleIds()` remains a derived accessor
- [ ] `ModePreset::toArray()` contains exactly eight fields
- [ ] `moduleIds` is absent from `toArray()`

#### Source execution isolation

- [ ] ordinary PHP output routed through the loader-owned buffer does not escape
- [ ] non-empty captured output fails with `mode-preset-source-output-forbidden`
- [ ] callback-handled PHP warnings, notices, and deprecations do not escape
- [ ] the original output-buffer level is restored on supported success and failure paths
- [ ] output buffers that existed before loading remain open
- [ ] the previous error handler is restored on supported success and failure paths
- [ ] raw warning text and source paths are absent from deterministic diagnostics
- [ ] verification does not claim containment of direct `STDOUT`/`STDERR` writes or deliberate output-buffer/error-handler manipulation

#### Documentation and implementation alignment

- [ ] source field set and order match `ModePreset::toArray()`
- [ ] schema version matches `ModePresetInterface::SCHEMA_VERSION`
- [ ] field limits match `ModePresetSchemaValidator` and `ModePreset`
- [ ] depth boundary tests use the exact container-depth counting rule:
  - [ ] depth `16` container is accepted
  - [ ] depth `17` container is rejected
- [ ] both lists and maps reject more than `256` entries
- [ ] source-only canonical rules are distinguished from loaded-state semantic rules
- [ ] lookup precedence matches `FilesystemModePresetLoader`
- [ ] path resolution matches `ModePresetLoaderFactory`
- [ ] failure reasons match `ModePresetInvalidException`
- [ ] packaging paths match `kernel.modes.defaults_path` and `kernel.modes.overrides_path`

#### Scope proof

- [ ] no public method signature changes
- [ ] no package dependency changes
- [ ] no config root or config key changes
- [ ] no ErrorCode changes
- [ ] no Composer command changes
- [ ] no gate-chain changes
- [ ] only the two approved stable reason tokens are added

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/kernel/tests/Contract/ModePresetCanonicalSourceRoundTripContractTest.php`
    - [ ] loads all four framework-owned canonical PHP preset payloads
    - [ ] validates each payload through `ModePresetSchemaValidator`
    - [ ] asserts strict identity:
      - [ ] `$preset->toArray() === $payload`
    - [ ] asserts canonical top-level key order
    - [ ] asserts all module-id source lists are canonical, unique, and `strcmp`-sorted
    - [ ] asserts all recursive map keys are canonical and `strcmp`-sorted
    - [ ] asserts `moduleIds` is absent from source payload and `toArray()`

- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/ModePresetSchemaValidatorRejectsNonCanonicalSourceTest.php`
    - [ ] data-provider coverage for:
      - [ ] reordered top-level keys
      - [ ] uppercase/non-canonical module-id source string
      - [ ] duplicate module id within one list
      - [ ] unsorted module-id list
      - [ ] unsorted `featureBundles` map keys
      - [ ] unsorted nested map keys
      - [ ] unsorted `metadata` map keys
    - [ ] every case fails with:
      - [ ] `CORETSIA_MODE_PRESET_INVALID`
      - [ ] `mode-preset-source-not-canonical`
    - [ ] semantic validation reasons retain precedence over source canonicality:
      - [ ] invalid module-id syntax remains `mode-preset-module-id-invalid`
      - [ ] cross-list overlap remains `mode-preset-sets-overlap`
      - [ ] unsafe metadata remains its existing safety reason

  - [ ] `framework/packages/core/kernel/tests/Integration/ModePresetLoaderSourceIsolationTest.php`
    - [ ] creates isolated temporary defaults and overrides directories
    - [ ] removes every temporary source file and directory in `finally`
    - [ ] ordinary output case:
      - [ ] source executes `echo` before returning a valid canonical payload
      - [ ] no captured source byte reaches the test output or an outer output buffer
      - [ ] load fails with:
        - [ ] `CORETSIA_MODE_PRESET_INVALID`
        - [ ] `mode-preset-source-output-forbidden`
      - [ ] captured source bytes are absent from message and context
    - [ ] source diagnostic case:
      - [ ] source triggers a warning or user warning before returning
      - [ ] the PHP diagnostic is not emitted
      - [ ] load fails with:
        - [ ] `CORETSIA_MODE_PRESET_INVALID`
        - [ ] `mode-preset-invalid`
      - [ ] raw warning text is absent from deterministic message and context
    - [ ] failure-precedence case:
      - [ ] source emits output and then throws
      - [ ] no captured output escapes
      - [ ] execution failure wins over output failure
      - [ ] result reason is `mode-preset-invalid`
    - [ ] `tryLoad()` behavior:
      - [ ] an absent preset still returns `null`
      - [ ] an existing output-producing source does not return `null`
      - [ ] an existing output-producing source throws the same deterministic invalid-preset failure as `load()`
      - [ ] an existing warning-producing or throwing source throws the same generic invalid-source failure as `load()`
    - [ ] output-buffer restoration:
      - [ ] test creates an outer output buffer before loading
      - [ ] test records the original output-buffer level
      - [ ] success restores the original level
      - [ ] output failure restores the original level
      - [ ] source execution failure restores the original level
      - [ ] pre-existing outer-buffer content remains unchanged
    - [ ] error-handler restoration:
      - [ ] test installs a pre-existing error handler before loading
      - [ ] success restores that handler
      - [ ] output failure restores that handler
      - [ ] source execution failure restores that handler
    - [ ] supported-boundary assertion:
      - [ ] tests cover ordinary buffered PHP output and callback-handled PHP diagnostics
      - [ ] tests MUST NOT claim containment of deliberate direct `STDOUT`/`STDERR` writes
      - [ ] tests MUST NOT claim reconstruction of caller-owned buffers deliberately destroyed by arbitrary source code

### DoD (MUST)

- [ ] Epic `2.10.0` is classified as implementation work rather than documentation-only work.
- [ ] Kernel-owned PHP mode preset source contract has one dedicated SSoT.
- [ ] The dedicated SSoT matches the hardened executable implementation.
- [ ] Contracts-level `docs/ssot/modes.md` remains source-format neutral.
- [ ] ADR-0024 references the dedicated source SSoT.
- [ ] PACKAGING documents framework and project ownership without hardcoding loader internals.
- [ ] Raw PHP source payload has exactly eight required top-level keys.
- [ ] Top-level key insertion order is canonical and enforced.
- [ ] Unknown, missing, non-string, reordered, and wrapped top-level shapes are rejected.
- [ ] `moduleIds` is forbidden in source payloads.
- [ ] Every accepted source module-id string is already canonical.
- [ ] Non-canonical ASCII casing is rejected.
- [ ] Source module-id lists contain no duplicates.
- [ ] Source module-id lists are byte-order `strcmp`-sorted.
- [ ] `required`, `optional`, and `disabled` are pairwise disjoint.
- [ ] Semantic overlap failures take precedence over generic canonical-source failures.
- [ ] Raw source maps are recursively byte-order `strcmp`-sorted.
- [ ] Nested list order is preserved.
- [ ] Recursive JSON-like safety and resource limits match the implementation.
- [ ] Floats, objects, closures, resources, unsafe strings, unsafe keys, and path-like values remain rejected.
- [ ] Every accepted source satisfies:
  - [ ] `validate($name, $payload)->toArray() === $payload`
- [ ] Semantically equivalent but non-canonical source is rejected with:
  - [ ] `mode-preset-source-not-canonical`
- [ ] `ModePreset` rejects duplicate direct-construction module identities.
- [ ] `ModePreset` rejects pairwise set overlap.
- [ ] `ModePreset` may canonicalize non-semantic direct-construction order.
- [ ] `ModePreset::toArray()` exports exactly eight canonical source-state fields.
- [ ] `moduleIds()` remains a derived compatibility accessor.
- [ ] `moduleIds` is absent from source payload and `toArray()`.
- [ ] No redundant module-id projection is added to canonical exported state.
- [ ] Ordinary PHP output routed through the loader-owned buffer is captured, discarded, and rejected.
- [ ] Non-empty captured output fails with:
  - [ ] `mode-preset-source-output-forbidden`
- [ ] Callback-handled PHP warnings, notices, and deprecations do not escape source execution.
- [ ] The original output-buffer level and previous error handler are restored on every supported success and failure path.
- [ ] Pre-existing caller-owned output buffers remain open.
- [ ] The documentation does not claim containment of direct-stream writes or deliberate output-buffer/error-handler manipulation.
- [ ] The documentation does not claim full arbitrary-PHP sandboxing.
- [ ] `hybrid.php` optional module ids are canonical and `strcmp`-sorted.
- [ ] `enterprise.php` optional module ids are canonical and `strcmp`-sorted.
- [ ] All four framework preset files pass the canonical round-trip test.
- [ ] Framework-owned presets remain under `framework/packages/core/kernel/resources/modes/*.php`.
- [ ] Default skeleton ships no `skeleton/config/modes/*.php` files.
- [ ] Project-owned overrides remain allowed under `skeleton/config/modes/*.php`.
- [ ] Framework and project-owned sources use the same strict schema.
- [ ] Skeleton override precedence and no-merge behavior remain unchanged.
- [ ] No public API signature changes.
- [ ] No package dependency changes.
- [ ] No config root or config key changes.
- [ ] No ErrorCode changes.
- [ ] No Composer command or gate-chain changes.
- [ ] Existing mode-preset lookup, path-resolution, and failure-precedence contracts remain intact.
- [ ] `docs/ssot/mode-preset-sources.md` is registered exactly once in `docs/ssot/INDEX.md`.
- [ ] The SSoT index entry is in deterministic relative-path order.
- [ ] The index and document both declare `ssotVersion: 1`.
- [ ] Ordinary PHP output successfully routed through the loader-owned buffer is captured, discarded, and rejected.
- [ ] The epic does not claim containment of arbitrary direct-stream or output-buffer manipulation.
- [ ] Source execution failure takes precedence over captured-output failure.
- [ ] Captured-output failure takes precedence over payload validation.
- [ ] Semantic validation takes precedence over canonical representation validation.
- [ ] Raw previous Throwable details are absent from every deterministic diagnostic surface.
- [ ] Existing module-resolution previous-Throwable chaining policy remains unchanged.
- [ ] JSON-like depth counting uses container depth with the root map at depth `0`.
- [ ] Depth `16` is accepted and depth `17` is rejected.
- [ ] The `256`-entry limit applies to both lists and maps.

---

### 2.20.0 Kernel fixtures for mode presets (SHOULD) [IMPL]

---
type: package
phase: 2
epic_id: "2.20.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Додати kernel-owned fixture trees для deterministic boot/e2e тестів режимів без потреби в skeleton overrides."
provides:
- "Fixture trees для Micro/Express/Hybrid/Enterprise (kernel-owned)"
- "Test-ready scaffolding to validate mode preset behavior deterministically"
- "No change to packaging policy: skeleton ships no default modes"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/modes.md"
- "docs/ssot/config-roots.md"   # subtree rule for fixture config files
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 2.10.0 — modes SSoT + packaging enforcement gate

- Required deliverables (exact paths):
  - `framework/packages/core/kernel/resources/modes/micro.php`
  - `framework/packages/core/kernel/resources/modes/express.php`
  - `framework/packages/core/kernel/resources/modes/hybrid.php`
  - `framework/packages/core/kernel/resources/modes/enterprise.php`
  - `docs/ssot/modes.md`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- none (fixtures only)

Forbidden:
- none

### Deliverables (MUST)

#### Creates

Kernel-owned fixture trees (tests-only; deterministic content; LF-only):

- [ ] `framework/packages/core/kernel/tests/Fixtures/_POLICY.md`
  - [ ] MUST state:
    - [ ] fixtures are tests-only
    - [ ] LF-only, final newline
    - [ ] no absolute paths, no machine-specific bytes
    - [ ] MUST NOT ship `config/modes/*` anywhere inside fixtures
    - [ ] config files follow subtree rule (no root wrapper)

- [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/README.md`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/config/modules.php`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/config/kernel.php` (optional minimal subtree)

- [ ] `framework/packages/core/kernel/tests/Fixtures/ExpressApp/`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/ExpressApp/README.md`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/ExpressApp/config/modules.php`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/ExpressApp/config/kernel.php` (optional minimal subtree)

- [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/README.md`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/modules.php`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/kernel.php` (optional minimal subtree)

- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/README.md`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/kernel.php` (optional minimal subtree)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/ModePresetResourcesExistAndReturnArrayTest.php`
    - [ ] MUST only assert file presence + `is_array(require ...)`

  - [ ] `framework/packages/core/kernel/tests/Unit/ModeFixturesDoNotShipModeOverridesTest.php`
    - [ ] Assert: no `tests/Fixtures/**/config/modes/*` present

  - [ ] `framework/packages/core/kernel/tests/Unit/ModeFixtureConfigFilesReturnArrayTest.php`
    - [ ] For each fixture app dir:
      - [ ] assert `config/modules.php` exists and `is_array(require ...)`
      - [ ] if `config/kernel.php` exists, assert `is_array(require ...)`
    - [ ] MUST NOT assert any machine-specific values; presence + type only

### DoD (MUST)

- [ ] Fixture trees exist (paths exact)
- [ ] Fixtures deterministic (LF-only, no machine-specific content)
- [ ] Fixtures do not include any `config/modes/*`
- [ ] Skeleton default `skeleton/config/modes/*` still forbidden (enforced by 2.10.0 gate)

---

### 2.25.0 Kernel ops façade for CLI (MUST) [IMPL]

---
type: package
phase: 2
epic_id: "2.25.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Надати platform/cli стабільні Kernel-owned операції для explicit app target із preset, визначеним Bootstrap Phase A configuration."
provides:
- "Kernel-owned Ops façade over existing compile-host services"
- "Explicit app-target input for every module-aware operation"
- "Single preset source: BootstrapConfigResolver resolves presets[appTarget], global preset, or package default"
- "Generation-aware compile result for one atomically selected immutable generation"
- "Graph-bound expected generation ID calculation without artifact writes"
- "Current-generation verification across all four generation files"
- "Stable json-like result DTOs safe for platform rendering"
- "Single ModuleResolution snapshot for each operation"
- "No direct artifact, fingerprint, module, provider, or filesystem orchestration in platform/cli"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []     # uses existing kernel artifacts; does not introduce new artifact schemas
adr: none
ssot_refs:
- "docs/ssot/cache-verify.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/artifacts-and-fingerprint.md"
- "docs/ssot/artifact-generations.md"
- "docs/ssot/compiled-container.md"
- "docs/ssot/runtime-container-definitions.md"
- "docs/ssot/modules-and-manifests.md"
- "docs/ssot/config-and-env.md"
- "docs/ssot/modes.md"
- "docs/ssot/observability.md"
- "docs/ssot/context-keys.md"
- "docs/ssot/context-store.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Kernel operation dependencies exist and are wired:
  - `ConfigKernel` is available for validate/debug flows;
  - `ArtifactCompiler` is available for immutable-generation publication;
  - `CacheVerifier` is available for current-generation verification;
  - `RuntimeContainerGraphCompiler` is available for canonical graph production;
  - `ConfigFingerprintInputBuilder` is available for graph-bound fingerprint input construction;
  - `FingerprintCalculator` is available only for hashing an already-built canonical fingerprint input;

- Canonical generation infrastructure exists:
  - `ArtifactGenerationPublisher` publishes one immutable generation and atomically replaces `current`;
  - `ArtifactGenerationLocator` resolves and validates the generation selected by `current`;
  - `CacheVerifier` compares the expected generation against all four selected-generation files;
  - no operation selects a generation by scanning `generations/`.

- Kernel source/operations-host boot:
  - MUST resolve compile-host and source-runtime services without an existing artifact generation;
  - `config:compile` MUST work when no artifact root or `current` pointer exists;
  - `config:hash` MUST neither require nor read `current`;
  - `cache:verify` MUST classify an absent `current` generation as a completed dirty/missing result;
  - MUST remain separate from `ArtifactRuntimeBooter`;
  - MUST NOT become a source fallback for HTTP or Worker production runtime.

- Existing Kernel configuration pipeline:
  - [ ] `KernelServiceProvider` and `KernelServiceFactory` already wire:
    - [ ] Bootstrap Phase A services
    - [ ] `EnvRepositoryBuilder`
    - [ ] `ModulePlanResolver`
    - [ ] `ConfigKernel`
    - [ ] existing config loaders
    - [ ] `ConfigMerger`
    - [ ] `ConfigValidator`
    - [ ] `ConfigExplainer`
    - [ ] artifact compilation and verification services
  - [ ] `ConfigKernel` remains the sole Config Phase B orchestration entrypoint
  - [ ] `ArtifactCompiler` remains the compile and publication orchestrator
  - [ ] `CacheVerifier` remains the current-generation verification orchestrator
  - [ ] this epic introduces no separate public config-location capability or prerequisite epic
  - [ ] this epic introduces exactly one internal compile-host argument-preparation helper
  - [ ] no public source-plan API or source-plan DTO is introduced
  - [ ] no parallel config loader, merger, validator, explainer, or repository implementation is introduced

- Compiled application runtime is not a dependency of Kernel Ops:
  - `KernelOpsFacade` is compile-host-only;
  - `KernelOpsFacade` and `KernelOpsInterface` MUST NOT enter the compiled runtime definition graph;
  - Kernel operations MUST NOT boot the application through `ArtifactRuntimeBooter`.

- Required contracts / ports (exact FQCNs) (MUST)
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Config\ConfigValidatorInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Context\ContextKeys`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Foundation\Time\Stopwatch`

- Cross-package deliverable (embedded into 2.25) — new ports introduced in `core/contracts`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsRequest`
  - `Coretsia\Contracts\Kernel\Ops\OpsResult`
  - `Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException`

- Boundary note (single-choice):
  - this epic intentionally co-introduces the public contracts port required by the same kernel capability
  - the ONLY allowed cross-package deliverables outside `framework/packages/core/kernel/` in this epic are:
    - `framework/packages/core/contracts/src/Kernel/Ops/KernelOpsInterface.php`
    - `framework/packages/core/contracts/src/Kernel/Ops/KernelOpsRequest.php`
    - `framework/packages/core/contracts/src/Kernel/Ops/OpsResult.php`
    - `framework/packages/core/contracts/src/Kernel/Ops/Exception/KernelOpsFailedException.php`
    - `docs/ssot/observability.md`
  - this epic MUST NOT introduce unrelated `core/contracts` surface beyond the Kernel Ops port

### Kernel operation target and preset ownership (MUST)

Every operation receives:

```php
final readonly class KernelOpsRequest
{
    public function __construct(
        string $appTarget,
    );

    public function appTarget(): string;
}
```

Canonical targets:

```text
web
api
console
worker
```

`KernelOpsFacade` MUST construct target Bootstrap input without a preset override:

```php
new BootstrapInput(
    skeletonRoot: $skeletonRoot,
    appTarget: AppTarget::fromString($request->appTarget()),
    preset: null,
);
```

Effective preset ownership remains:

```text
skeleton/config/app.php presets[appTarget]
→ skeleton/config/app.php preset
→ kernel.boot.default_preset
```

Kernel Ops MUST NOT accept, infer, or synthesize a preset override.

`OpsResult::preset()` returns the nullable effective preset; null is allowed only for a handled error produced before preset resolution completes.

The operations host uses `AppTarget::Console` only for CLI service composition. It MUST NOT replace the operation target.

### Kernel operations orchestration ownership (MUST)

`platform/cli` is a transport and presentation layer for Kernel operations.

CLI command classes MUST NOT directly orchestrate module resolution, provider planning, container definition collection, config compilation, artifact compilation, fingerprint calculation, or cache verification.

Canonical command-side flow:

```text
DebugModulesCommand
ConfigValidateCommand
ConfigDebugCommand
ConfigCompileCommand
ConfigHashCommand
CacheVerifyCommand
    -> Coretsia\Contracts\Kernel\Ops\KernelOpsInterface
```

Canonical Kernel-side operation flow:

```text
KernelOpsFacade
    -> KernelOpsRequest(appTarget)
    -> BootstrapInput(
           appTarget = explicit request target,
           preset = null
       )
    -> existing BootstrapConfigResolver
    -> existing EnvRepositoryBuilder
    -> existing ModulePlanResolver::resolveResolution()
    -> one ModuleResolution
    -> existing ConfigKernel Phase B when configuration is required
    -> existing operation-specific Kernel service
    -> safe generation-aware OpsResult
```

`KernelOpsFacade` composes existing Kernel services. It does not provide a new config loading or repository implementation.

Operation-specific ownership:

```text
validateConfig()
    -> ConfigKernel validation

debugConfig()
    -> ConfigKernel safe explain output

debugModules()
    -> safe ModuleResolution and ModulePlan summary

compileConfig()
    -> ArtifactCompiler
    -> ArtifactGenerationPublisher
    -> published ArtifactGeneration

hashConfig()
    -> ConfigKernel
    -> RuntimeContainerGraphCompiler
    -> ConfigFingerprintInputBuilder
    -> FingerprintCalculator
    -> expected generation ID
    -> no writes and no current-generation read

verifyCache()
    -> CacheVerifier
    -> expected generation reconstruction
    -> current-generation location and validation
    -> four-file generation comparison
```

For one invocation of any target-aware Kernel operation:

- `ModulePlanResolver::resolveResolution()` MUST be invoked at most once;
- `ModulePlanResolver::resolve()` MUST NOT be used because it discards the installed manifest snapshot;
- `ManifestReaderInterface::read()` MUST NOT be invoked again after `resolveResolution()` returns;
- the same `ModuleResolution` instance MUST be supplied to every downstream component that requires module or provider context;
- the same `ModuleResolution::plan()` instance MUST be supplied to ConfigKernel and fingerprint-input construction;
- `RuntimeContainerGraphCompiler` MUST receive that same `ModuleResolution`;
- `RuntimeContainerGraphCompiler` owns `ContainerProviderPlanResolver` invocation and provider-definition collection;
- `KernelOpsFacade` MUST NOT resolve a separate provider plan before invoking `ArtifactCompiler`, `CacheVerifier`, or `RuntimeContainerGraphCompiler`;
- provider definitions MUST be collected exactly once in canonical provider-plan order for each graph-producing operation;
- `ModuleResolution` and `ContainerProviderPlan` MUST remain compile-time values and MUST NOT be exported into artifacts;
- `ModulePlan` MUST NOT contain provider class lists;
- `KernelOpsInterface` and `OpsResult` MUST NOT expose `ModuleResolution`, `ContainerProviderPlan`, provider instances, raw Composer metadata, raw config/env values, or absolute paths.

`ArtifactCompiler` and `CacheVerifier` receive an already-resolved `ModuleResolution` and MUST NOT depend on `ModulePlanResolver`, `ManifestReaderInterface`, or `ComposerManifestReader`.

`RuntimeContainerGraphCompiler` owns `ContainerProviderPlanResolver` and consumes the supplied `ModuleResolution`.

`FingerprintCalculator` receives only the already-built canonical fingerprint input. It MUST NOT compile config, compile a container graph, resolve modules, plan providers, locate generations, or read artifacts.

Kernel Ops results MUST be safe by construction. `core/kernel` MUST NOT depend on `platform/redaction` or `SensitiveDataRedactorInterface` to make an unsafe `OpsResult` suitable for CLI rendering.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/*`
- `integrations/*`

### Entry points / integration points (MUST)

- Public Kernel operations service for `platform/cli`:
  - resolved from the Kernel source/operations host container;
  - registered as compile-host wiring by `KernelServiceProvider::register()`;
  - MUST NOT enter canonical runtime definitions produced by `KernelServiceProvider::define()`;
  - MUST NOT be exported into compiled container artifacts;
  - exposes only `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`;
  - performs no stdout/stderr writes;
  - exposes deterministic safe exceptions and result DTOs.

#### Configuration (MUST)

- [ ] This epic introduces no `kernel.operation.*` config subtree.
- [ ] Kernel Ops consumes only existing target-specific Bootstrap, env, mode, module, and Kernel configuration.
- [ ] Effective preset is resolved exclusively through `BootstrapConfigResolver`.
- [ ] `KernelOpsFacade` MUST NOT read `cli.*`.
- [ ] Observability, context access, and UoW participation MUST NOT be controlled by Kernel Ops feature flags.
- [ ] No config key may disable result safety, safe-shape-by-construction rules, tracing, metrics, or context boundary rules.

#### Existing configuration pipeline reuse (MUST)

- [ ] Kernel Ops reuses the existing Bootstrap Phase A and ConfigKernel Phase B implementations.
- [ ] Existing ownership remains unchanged:
  - [ ] `BootstrapConfigResolver` owns Bootstrap Phase A resolution
  - [ ] `EnvRepositoryBuilder` owns immutable env snapshot construction
  - [ ] `ModulePlanResolver::resolveResolution()` owns target preset and enabled-module resolution
  - [ ] `ConfigKernel` owns config loading, directives, merge, validation, and explain
  - [ ] `RuntimeContainerGraphCompiler` owns provider planning and graph compilation
  - [ ] `ArtifactCompiler` owns artifact generation and publication
  - [ ] `CacheVerifier` owns current-generation verification
- [ ] Kernel Ops MUST NOT introduce:
  - [ ] a package config loader
  - [ ] a skeleton config loader
  - [ ] a rules loader
  - [ ] a config merger
  - [ ] a config validator
  - [ ] a config explainer
  - [ ] a second `ConfigRepositoryInterface` implementation
  - [ ] a parallel config source-plan DTO
  - [ ] a second mode-preset resolver
  - [ ] a separate config-location epic
- [ ] The explicit source-candidate arguments already required by `ConfigKernel`, `ArtifactCompiler`, and `CacheVerifier` remain orchestration inputs to those existing APIs.
- [ ] Preparing those arguments MUST NOT implement config loading, merge, validation, or explain semantics.
- [ ] No new public contract, config root, artifact schema, or package-level configuration mechanism is introduced for those arguments.
- [ ] The operations-host `ConfigRepositoryInterface` represents the validated console-host configuration.
- [ ] It MAY be consumed by console-host package factories and services.
- [ ] It MUST NOT be treated as the target configuration for a Kernel operation whose request target is `web|api|worker`.
- [ ] Every target-aware Kernel operation resolves and compiles configuration for its explicit request target through the existing Phase A and Phase B pipeline.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/kernel/src/Config/CompileHostConfigInputBuilder.php`
  - [ ] internal readonly/stateless helper
  - [ ] is not a public contract
  - [ ] introduces no DTO
  - [ ] canonical seed API:
    - [ ] `/** @return array{foundation: array<string, mixed>, kernel: array<string, mixed>}*/`
    - [ ] `public static function seedConfig(): array`
  - [ ] `seedConfig()` loads exactly:
    - [ ] `coretsia/core-foundation/config/foundation.php`
    - [ ] `coretsia/core-kernel/config/kernel.php`
  - [ ] package install roots are resolved only through `Composer\InstalledVersions::getInstallPath()`
  - [ ] performs no directory scanning
  - [ ] validates that both files return map arrays
  - [ ] reads no skeleton, app, environment, preset, or generated-artifact files
  - [ ] canonical operation-input API:
    - [ ] `/** @return array{kernelConfig: array<string, mixed>, packageDefaultSources: list<array<string, mixed>>, packageRuleSources: list<array<string, mixed>>, splitRoots: list<string>, explicitRuleSources: list<array<string, mixed>>, explicitEnvOverlayMappings: list<array<string, mixed>>, modePresetSourceCandidates: list<array<string, mixed>>} */`
    - [ ] `public function build(BootstrapConfig $bootstrapConfig, ModuleResolution $moduleResolution): array`
  - [ ] consumes only the supplied `BootstrapConfig` and `ModuleResolution`
  - [ ] obtains enabled modules only from `ModuleResolution::plan()`
  - [ ] obtains package descriptors only from `ModuleResolution::manifest()`
  - [ ] MUST NOT invoke `ModulePlanResolver`
  - [ ] MUST NOT invoke `ManifestReaderInterface`
  - [ ] MUST NOT read the Composer manifest a second time
  - [ ] resolves package install roots without package-directory scanning
  - [ ] package defaults use only descriptor `defaultsConfigPath`
  - [ ] package rules use only package-owned `config/rules.php`
  - [ ] produces deterministic canonical source order
  - [ ] performs no config source loading
  - [ ] performs no directives, merge, validation, explain, fingerprint, graph, or artifact operations

- [ ] `framework/packages/core/contracts/src/Kernel/Ops/KernelOpsRequest.php`
  - [ ] readonly value object
  - [ ] contains only `appTarget: string`
  - [ ] rejects empty, multiline, or control-byte input
  - [ ] performs no Kernel-specific target validation
  - [ ] contains no mode, preset, paths, config, or artifact state

- [ ] `framework/packages/core/contracts/src/Kernel/Ops/KernelOpsInterface.php`
  - [ ] Methods (single-choice; deterministic; no stdout/stderr):
    - [ ] `validateConfig(KernelOpsRequest $request): OpsResult`
    - [ ] `debugConfig(KernelOpsRequest $request): OpsResult`
    - [ ] `compileConfig(KernelOpsRequest $request): OpsResult`
    - [ ] `hashConfig(KernelOpsRequest $request): OpsResult`
    - [ ] `verifyCache(KernelOpsRequest $request): OpsResult`
    - [ ] `debugModules(KernelOpsRequest $request): OpsResult`
  - [ ] is a narrow port for Kernel-owned operations only
  - [ ] MUST NOT become a generic CLI command bus
  - [ ] MUST NOT acquire worker, migration, database, queue, storage, or integration-owned methods

- [ ] `framework/packages/core/contracts/src/Kernel/Ops/OpsResult.php`
  - [ ] Immutable DTO / readonly:
    - [ ] `schemaVersion: int`
    - [ ] `operation: string`
    - [ ] `appTarget: string`
    - [ ] `preset: ?string` — effective preset resolved by `BootstrapConfigResolver`
    - [ ] `preset` MUST be non-null for every successful operation result
    - [ ] `preset` MAY be null only for `handled_error` produced before effective preset resolution completes
    - [ ] `outcome: string` (`success|handled_error`)
    - [ ] `success` requires a non-null effective preset
    - [ ] `handled_error` represents an expected, safely classified operation rejection
    - [ ] unexpected internal failures MUST throw `KernelOpsFailedException`
    - [ ] `reason: string` — stable safe reason token
    - [ ] `data: array` — json-like; no floats; maps recursively `strcmp`-sorted; list order preserved
    - [ ] `fatal_error` MUST NOT be represented both as a result and an exception
  - [ ] MUST NOT include raw values or absolute filesystem paths
  - [ ] MAY include only safe tokens, canonical ids, basenames, counts, hashes, lengths, and the `ConfigExplainer`-normalized repo-relative or logical source paths explicitly allowed for `debugConfig()`
  - [ ] `compileConfig()` success data contains exactly:
    - [ ] `'artifacts' => list<array{basename: string, identity: string}>`
    - [ ] `'generation_id' => string`
  - [ ] `compileConfig()` artifact list order and values are exactly:
    - [ ] `identity = module-manifest@1`, `basename = module-manifest.php`
    - [ ] `identity = config@1`, `basename = config.php`
    - [ ] `identity = container@1`, `basename = container.php`
    - [ ] `identity = artifact-generation@1`, `basename = generation-manifest.php`
  - [ ] `compileConfig()` data contains no filesystem paths
  - [ ] `hashConfig()` success data contains exactly:
    - [ ] `'generation_id' => string`
  - [ ] `hashConfig()` performs no artifact writes or current-generation reads
  - [ ] `verifyCache()` success data contains exactly:
    - [ ] `'artifacts' => list<array{basename: string, existing_byte_count: int|null, expected_byte_count: int, identity: string, reason: string, status: string}>`
    - [ ] `'current_generation_id' => string|null`
    - [ ] `'expected_generation_id' => string`
    - [ ] `'state' => 'clean'|'dirty'|'invalid'`
  - [ ] `verifyCache()` artifact list order and identities match `compileConfig()`
  - [ ] artifact `status` values are exactly `clean|dirty|invalid`
  - [ ] artifact `reason` values are exactly `ok|missing|changed|fingerprint_mismatch|invalid`
  - [ ] `verifyCache()` data contains no filesystem paths
  - [ ] `validateConfig()` data contains only validation status and counts:
    - [ ] `counts.unvalidated_root_count` => int
    - [ ] `counts.validated_root_count` => int
    - [ ] `counts.violation_count` => int
    - [ ] `valid` => bool
  - [ ] `debugConfig()` success data contains exactly:
    - [ ] `explain` => `<the non-null safe explain result returned by ConfigKernel>`
  - [ ] `debugConfig()` MAY preserve repo-relative or logical source paths only when they are already normalized by `ConfigExplainer`
  - [ ] `KernelOpsFacade` MUST apply only the generic recursive json-like normalization required by `OpsResult`
  - [ ] `KernelOpsFacade` MUST NOT remove, reconstruct, enrich, or join explain fields with raw config, env, filesystem, or Composer data
  - [ ] absolute filesystem paths remain forbidden
  - [ ] `debugModules()` contains only safe ModulePlan summary:
    - [ ] `'disabled' => list<string>`
    - [ ] `'enabled' => list<string>`
    - [ ] `'optional_missing' => list<string>`
    - [ ] `'topological_order' => list<string>`
    - [ ] `'warnings' => list<array{code: string, module_id: string, reason: string}>`
  - [ ] `debugModules()` MUST NOT call `ModulePlan::toArray()` directly because the current exported shape contains Composer-owned module metadata

- [ ] `framework/packages/core/contracts/src/Kernel/Ops/Exception/KernelOpsFailedException.php`
  - [ ] Deterministic code-first; message safe (no secrets/abs paths)
  - [ ] Intended for catch/handling in `platform/*` without `Coretsia\Kernel\*` imports

- [ ] `framework/packages/core/kernel/src/Ops/KernelOpsFacade.php`
  - [ ] MUST `implements Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - [ ] constructor receives the exact `KernelOpsHostInput` seeded by `KernelOpsHostBooter`
  - [ ] constructor receives operation services explicitly:
    - [ ] `BootstrapConfigResolver`
    - [ ] `EnvRepositoryBuilder`
    - [ ] `ModulePlanResolver`
    - [ ] `ConfigKernel`
    - [ ] `RuntimeContainerGraphCompiler`
    - [ ] `ConfigFingerprintInputBuilder`
    - [ ] `FingerprintCalculator`
    - [ ] `ArtifactCompiler`
    - [ ] `CacheVerifier`
    - [ ] `CompileHostConfigInputBuilder`
  - [ ] uses `KernelOpsHostInput::skeletonRoot()` as the sole skeleton root for every target-specific `BootstrapInput`
  - [ ] constructor receives:
    - [ ] `ContextAccessorInterface`
    - [ ] `TracerPortInterface`
    - [ ] `MeterPortInterface`
    - [ ] `LoggerInterface`
    - [ ] Foundation `Stopwatch`
  - [ ] MUST NOT instantiate noop logger, tracer, or meter directly
  - [ ] MUST NOT read observability services from a service locator
  - [ ] MUST NOT derive the skeleton root from CWD, argv, environment variables, artifacts, or target config
  - [ ] MUST return `Coretsia\Contracts\Kernel\Ops\OpsResult` (contracts DTO; no kernel-local duplicate DTO)
  - [ ] MUST throw `Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException` (contracts exception; no kernel-local duplicate exception)
  - [ ] maps `expectedGenerationId` and `currentGenerationId` into `OpsResult`
  - [ ] removes every lower-level artifact `path` field
  - [ ] preserves `ConfigExplainer`-normalized repo-relative or logical source paths only inside `debugConfig().data.explain`
  - [ ] MUST delegate to existing Kernel components:
    - [ ] `BootstrapConfigResolver`
    - [ ] `EnvRepositoryBuilder`
    - [ ] `ModulePlanResolver::resolveResolution()`
    - [ ] `ConfigKernel`
    - [ ] `RuntimeContainerGraphCompiler`
    - [ ] `ConfigFingerprintInputBuilder`
    - [ ] `FingerprintCalculator`
    - [ ] `ArtifactCompiler`
    - [ ] `CacheVerifier`
  - [ ] MUST NOT print; MUST NOT leak raw config/env values; MUST NOT leak absolute paths
  - [ ] Results MUST be json-like (no floats; no objects/resources)
  - [ ] MUST own Kernel-side orchestration for:
    - [ ] `validateConfig()`
    - [ ] `debugConfig()`
    - [ ] `debugModules()`
    - [ ] `compileConfig()`
    - [ ] `hashConfig()`
      - [ ] failed config validation maps to safe `handled_error`
      - [ ] container graph and fingerprint calculation occur only after successful validation
      - [ ] passes the same Kernel config and source-provenance inputs required by the existing compile and verify fingerprint pipeline
      - [ ] MUST NOT define a separate hash-only config interpretation
      - [ ] `KernelOpsFacade` MUST inspect the `ConfigValidationResult` returned by the single `ConfigKernel::compile()` call
      - [ ] failed validation MUST be mapped to `handled_error` before graph compilation
      - [ ] `KernelOpsFacade` MUST NOT call `ConfigValidator` or repeat validation
    - [ ] `verifyCache()`
  - [ ] module-aware operations MUST use `ModulePlanResolver::resolveResolution()`
  - [ ] MUST NOT use `ModulePlanResolver::resolve()` for module-aware operations because it discards the installed manifest snapshot
  - [ ] MUST invoke `resolveResolution()` at most once per operation
  - [ ] canonical safe operation ids:
    - [ ] `config.validate`
    - [ ] `config.debug`
    - [ ] `config.compile`
    - [ ] `config.hash`
    - [ ] `cache.verify`
    - [ ] `modules.debug`
  - [ ] observability ownership:
    - [ ] creates one `kernel.operation` span per operation
    - [ ] span attributes are limited to:
      - [ ] `operation`
      - [ ] `app_target`
      - [ ] `preset`, only after effective preset resolution
      - [ ] `outcome`
    - [ ] `preset` is omitted when resolution did not complete
    - [ ] observability outcomes are exactly `success|handled_error|failure`
    - [ ] emits `kernel.operation_total`
      - [ ] labels: `operation|outcome`
    - [ ] emits `kernel.operation_duration_ms`
      - [ ] labels: `operation|outcome`
    - [ ] duration is measured only through Foundation `Stopwatch`
    - [ ] metric values are integer-based; floats are forbidden
    - [ ] `app_target` and `preset` MUST NOT be metric labels
    - [ ] generation ids, fingerprints, artifact names, paths, counts, and exception messages MUST NOT be metric labels
    - [ ] generation ids and fingerprints MUST NOT be span attributes
    - [ ] observability failures MUST NOT alter operation result or exception semantics
  - [ ] logging:
    - [ ] emits only a safe completion/failure summary
    - [ ] safe fields are limited to `operation|app_target|preset|outcome`
    - [ ] MAY include safe `correlation_id|uow_id` when available through `ContextAccessorInterface`
    - [ ] MUST NOT log raw config, env values, Composer metadata, artifact data, paths, generation ids, fingerprints, previous throwable messages, or stack traces
  - [ ] context reads:
    - [ ] `ContextKeys::CORRELATION_ID`
    - [ ] `ContextKeys::UOW_ID`
    - [ ] `ContextKeys::UOW_TYPE`
    - [ ] reads no other context keys
    - [ ] reads only through `ContextAccessorInterface`
    - [ ] missing context values are allowed and MUST NOT fail the operation
  - [ ] context writes:
    - [ ] MUST NOT write `ContextStore` directly
    - [ ] MUST NOT create correlation ids or UoW ids
    - [ ] MUST NOT replace `uow_type`
  - [ ] UoW boundary:
    - [ ] assumes the caller may already execute inside a canonical Kernel UoW
    - [ ] MUST NOT create a nested UoW
    - [ ] MUST NOT invoke `KernelRuntimeInterface`
    - [ ] MUST NOT invoke hooks or reset orchestration
    - [ ] MUST NOT enumerate `kernel.reset`
  - [ ] unsupported app target MUST return:
    - [ ] `outcome = handled_error`
    - [ ] `reason = app-target-invalid`
    - [ ] `preset = null`
  - [ ] invalid Bootstrap or target-preset selection MUST return a safe `handled_error`
  - [ ] unexpected implementation failures MUST throw `KernelOpsFailedException`
  - [ ] MUST pass the returned `ModuleResolution` directly to `ArtifactCompiler`, `CacheVerifier`, or `RuntimeContainerGraphCompiler` according to the selected operation
  - [ ] MUST NOT invoke `ContainerProviderPlanResolver` separately from `RuntimeContainerGraphCompiler`
  - [ ] MUST NOT invoke `ManifestReaderInterface::read()` after `resolveResolution()` returns
  - [ ] MUST use the same `ModuleResolution::plan()` instance throughout the complete operation
  - [ ] MUST NOT collect provider definitions directly
  - [ ] MUST pass the same `ModuleResolution` instance to `RuntimeContainerGraphCompiler`
  - [ ] provider planning and definition collection remain owned by `RuntimeContainerGraphCompiler`
  - [ ] MUST NOT expose `ModuleResolution`, `ContainerProviderPlan`, provider instances, raw Composer metadata, or absolute paths through `KernelOpsInterface` or `OpsResult`
  - [ ] `compileConfig()` MUST return the generation id produced by `ArtifactCompiler`
  - [ ] `compileConfig()` MUST report all four generation files
  - [ ] `hashConfig()` MUST use the same config, graph, and fingerprint-input pipeline as compile/verify
  - [ ] `hashConfig()` MUST NOT invoke `ArtifactCompiler`, `ArtifactGenerationPublisher`, `ArtifactGenerationLocator`, or `CacheVerifier`
  - [ ] `verifyCache()` MUST preserve Kernel clean/dirty/invalid classification
  - [ ] no operation may call `ArtifactRuntimeBooter`
  - [ ] every target-aware operation resolves exactly:
    - [ ] one target-specific `BootstrapConfig`
    - [ ] one `ModuleResolution`
  - [ ] configuration-aware operations resolve exactly one immutable `EnvRepositoryInterface`:
    - [ ] `validateConfig()`
    - [ ] `debugConfig()`
    - [ ] `compileConfig()`
    - [ ] `hashConfig()`
    - [ ] `verifyCache()`
  - [ ] configuration-aware operations invoke `CompileHostConfigInputBuilder::build()` exactly once
  - [ ] `debugModules()`:
    - [ ] MUST NOT invoke `EnvRepositoryBuilder`
    - [ ] MUST NOT invoke `CompileHostConfigInputBuilder`
    - [ ] MUST NOT load package config, config rules, skeleton config, dotenv, or generated artifacts
  - [ ] invokes only existing production APIs:
    - [ ] `validateConfig()` and `debugConfig()` invoke the existing `ConfigKernel`
    - [ ] `compileConfig()` invokes the existing `ArtifactCompiler`
    - [ ] `verifyCache()` invokes the existing `CacheVerifier`
    - [ ] `hashConfig()` uses the same existing ConfigKernel → graph → fingerprint pipeline without publication
  - [ ] passes the exact target-specific `BootstrapConfig` and `ModuleResolution`
  - [ ] passes the returned arguments unchanged to the existing Kernel services
  - [ ] MUST NOT construct package paths or source-candidate arrays directly
  - [ ] source-candidate preparation MUST NOT:
    - [ ] include or parse config files
    - [ ] execute package or skeleton config loaders directly
    - [ ] process directives
    - [ ] merge config values
    - [ ] validate config
    - [ ] build explain output
    - [ ] create a second repository
    - [ ] read the Composer manifest a second time
  - [ ] passes all prepared arguments unchanged to the existing Kernel services
  - [ ] MUST NOT substitute the console-host `ConfigRepositoryInterface` for target-specific Phase B compilation

- [ ] `framework/packages/core/kernel/src/Ops/KernelOpsHostInput.php`
  - [ ] readonly normalized `skeletonRoot`
  - [ ] contains no target, preset, config, or artifact paths
  - [ ] performs no filesystem reads
  - [ ] the exact normalized instance is seeded into the source operations container

- [ ] `framework/packages/core/kernel/src/Ops/KernelOpsHostBooter.php`
  - [ ] public stateless zero-constructor boot façade
  - [ ] canonical API:
    - [ ] `public function boot(KernelOpsHostInput $input): ContainerInterface`
  - [ ] may be constructed directly before any container exists
  - [ ] requires no generated artifacts or `current`
  - [ ] never calls `ArtifactRuntimeBooter`
  - [ ] bootstrap seed stage:
    - [ ] creates one seed `ContainerBuilder`
    - [ ] obtains the exact Foundation and Kernel seed configuration only through `CompileHostConfigInputBuilder::seedConfig()`
    - [ ] registers only canonical Foundation and Kernel bootstrap providers
    - [ ] applies them as one declarative-capable provider batch
    - [ ] builds one seed container
    - [ ] MUST NOT discover or register external package providers during the seed stage
  - [ ] resolves the console source-host state through existing Kernel services:
    - [ ] one console-target `BootstrapConfig`
    - [ ] one immutable `EnvRepositoryInterface`
    - [ ] one console `ModuleResolution`
    - [ ] one ConfigKernel Phase B result for the console host
    - [ ] after the single `ConfigKernel::compile()` invocation, inspect `$compiledConfig['validation']`
    - [ ] if `validation->isFailure()`, `throw ConfigInvalidException::fromValidationResult($compiledConfig['validation'])`
    - [ ] this is an assertion over the validation result already produced by `ConfigKernel`; it MUST NOT invoke `ConfigValidator` or repeat validation
    - [ ] the assertion MUST occur before:
      - [ ] `ArrayConfigRepository` construction
      - [ ] final `ContainerBuilder` creation
      - [ ] enabled-provider instantiation or registration
    - [ ] one validated console-host `ConfigRepositoryInterface`
    - [ ] one canonical provider plan
  - [ ] creates the console-host `ConfigRepositoryInterface` from the validated Phase B config result using the existing Kernel repository implementation
  - [ ] MUST NOT introduce another repository implementation
  - [ ] MUST NOT treat source-host config compilation as a Kernel operation command
  - [ ] MUST NOT emit `kernel.operation` observability for host construction
  - [ ] final source-host stage:
    - [ ] creates a separate final `ContainerBuilder`
    - [ ] uses the validated complete source configuration
    - [ ] seeds the exact `KernelOpsHostInput`
    - [ ] seeds canonical source values required by enabled providers:
      - [ ] console `BootstrapConfig`
      - [ ] `EnvRepositoryInterface`
      - [ ] `ConfigRepositoryInterface`
      - [ ] resolved `ModulePlan`
      - [ ] runtime path context
  - [ ] final provider application:
    - [ ] uses the canonical `ContainerProviderPlan`
    - [ ] instantiates enabled providers in exact provider-plan order
    - [ ] every provider selected for the final source-operations host MUST implement:
      - [ ] `ServiceProviderInterface`
      - [ ] `ContainerDefinitionProviderInterface`
    - [ ] `KernelOpsHostBooter` MUST validate both capabilities before any provider `register()` method is invoked
    - [ ] a definition-only provider is valid for production graph compilation but is not source-host-capable
    - [ ] a selected definition-only provider MUST cause a deterministic safe source-host boot failure
    - [ ] all validated dual-interface providers are supplied to `ContainerBuilder::registerProviders()` as one canonical batch in exact `ContainerProviderPlan` order
    - [ ] each provider contribution is collected exactly once
    - [ ] each builder applies exactly one complete definition set
    - [ ] no imperative-only module-provider lane exists
    - [ ] no second provider plan or provider discovery path exists
    - [ ] no package is special-cased by FQCN
  - [ ] returned container can resolve:
    - [ ] `KernelOpsInterface`
    - [ ] `KernelRuntimeInterface`
    - [ ] canonical logger, tracer, meter, context accessor, and stopwatch
    - [ ] source-host-only services contributed through `register()` by enabled dual-interface providers
    - [ ] commands contributed through enabled package providers
  - [ ] host boot itself MUST NOT:
    - [ ] require package config files directly
    - [ ] derive package config or rules paths
    - [ ] introduce a separate source-candidate DTO or config subsystem
    - [ ] load, merge, validate, or explain config outside the existing ConfigKernel pipeline
    - [ ] implement config merge or validation
    - [ ] create a command UoW
    - [ ] write runtime context values
    - [ ] execute a command
    - [ ] publish or verify artifacts
    - [ ] load generated compiled-container definitions or generated container artifacts
    - [ ] applying enabled providers through their source `define()` methods is required and is not artifact-runtime boot
  - [ ] failures are deterministic and MUST NOT expose absolute paths, config values, env values, provider instances, or previous Throwable messages

- [ ] Lower-level Kernel operation services:
  - [ ] `ArtifactCompiler` MUST receive already-resolved operation inputs
  - [ ] `FingerprintCalculator` MUST receive already-resolved operation inputs
  - [ ] `CacheVerifier` MUST receive already-resolved operation inputs
  - [ ] none of these services may depend on:
    - [ ] `ModulePlanResolver`
    - [ ] `ManifestReaderInterface`
    - [ ] `ComposerManifestReader`
    - [ ] `ContainerProviderPlanResolver`

#### Modifies

- [ ] `framework/packages/core/kernel/src/Module/ModePresetLoaderFactory.php`
  - [ ] add:
    - [ ] `public function sourceCandidatesFor(BootstrapConfig $bootstrapConfig): array`
  - [ ] returns the exact framework-default and skeleton-override candidates used by `createFor()`
  - [ ] `createFor()` and `sourceCandidatesFor()` MUST share one private path-resolution implementation
  - [ ] MUST NOT reimplement mode path resolution in `CompileHostConfigInputBuilder`

- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [ ] register `KernelOpsFacade` as compile-host/source-operations wiring
  - [ ] bind `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface::class` to `KernelOpsFacade::class`
  - [ ] registration and binding MUST remain in `register()`
  - [ ] `KernelOpsFacade` MUST NOT be contributed by `define()`
  - [ ] `KernelOpsFacade` and `KernelOpsInterface` MUST NOT enter the canonical runtime definition graph

- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [ ] add deterministic construction for `KernelOpsFacade`
  - [ ] wire explicit Kernel operation dependencies
  - [ ] factory construction MUST NOT execute module resolution, config compilation, fingerprint calculation, artifact writing, or cache verification
  - [ ] inject the seeded `KernelOpsHostInput` into `KernelOpsFacade`
  - [ ] inject `ContextAccessorInterface`
  - [ ] inject `TracerPortInterface`
  - [ ] inject `MeterPortInterface`
  - [ ] inject `LoggerInterface`
  - [ ] inject Foundation `Stopwatch`
  - [ ] inject every operation service listed by `KernelOpsFacade`
  - [ ] MUST NOT let `KernelOpsFacade` resolve operation services through `ContainerInterface`
  - [ ] MUST NOT read CLI configuration
  - [ ] MUST NOT construct noop observability implementations
  - [ ] MUST NOT execute observability during service construction

- [ ] `docs/ssot/observability.md`
  - [ ] register canonical span `kernel.operation`
  - [ ] register counter `kernel.operation_total`
  - [ ] register observation `kernel.operation_duration_ms`
  - [ ] metric labels are exactly `operation|outcome`
  - [ ] allowed `operation` values:
    - [ ] `config.validate`
    - [ ] `config.debug`
    - [ ] `config.compile`
    - [ ] `config.hash`
    - [ ] `cache.verify`
    - [ ] `modules.debug`
  - [ ] allowed outcome values:
    - [ ] `success`
    - [ ] `handled_error`
    - [ ] `failure`
  - [ ] `preset` span attribute is omitted when effective preset resolution did not complete
  - [ ] `app_target|preset` are allowed only as bounded span attributes
  - [ ] generation ids, fingerprints, artifact identities, paths, config values, and exception messages are forbidden labels and attributes

### Cross-cutting (MUST)

#### Context & UoW

- [ ] `KernelOpsFacade` is UoW-neutral:
  - [ ] works both with and without an already-active caller-owned UoW
  - [ ] MUST NOT invoke `KernelRuntimeInterface`
  - [ ] MUST NOT begin, finish, or nest a UoW
  - [ ] MUST NOT invoke lifecycle hooks
  - [ ] MUST NOT invoke reset orchestration
  - [ ] MUST NOT enumerate `kernel.reset`
- [ ] Context reads:
  - [ ] only through `ContextAccessorInterface`
  - [ ] allowed keys:
    - [ ] `ContextKeys::CORRELATION_ID`
    - [ ] `ContextKeys::UOW_ID`
    - [ ] `ContextKeys::UOW_TYPE`
  - [ ] missing keys are allowed
  - [ ] context values are used only for safe logging and observability correlation
  - [ ] operation results MUST NOT depend on context availability
- [ ] Context writes:
  - [ ] `KernelOpsFacade` MUST NOT import or resolve `ContextStore`
  - [ ] MUST NOT create correlation ids or UoW ids
  - [ ] MUST NOT replace `uow_type`
  - [ ] MUST NOT write operation target, preset, generation id, or fingerprint into runtime context
- [ ] State and reset:
  - [ ] `KernelOpsFacade` is stateless
  - [ ] `KernelOpsHostBooter` is stateless
  - [ ] no operation result, module resolution, config result, generation, or verification state is cached across calls
  - [ ] neither service implements `ResetInterface`
  - [ ] neither service is tagged `kernel.stateful` or `kernel.reset`

#### Observability

- [ ] Ownership:
  - [ ] `KernelOpsFacade` owns Kernel-operation observability
  - [ ] lower-level operation services MUST NOT emit duplicate `kernel.operation` lifecycle spans
  - [ ] transport adapters such as `platform/cli` MUST NOT emit duplicate Kernel-operation metrics
- [ ] Span:
  - [ ] name: `kernel.operation`
  - [ ] exactly one span per Kernel Ops invocation
  - [ ] safe attributes:
    - [ ] `operation`
    - [ ] `app_target`
    - [ ] `preset` only after effective preset resolution
    - [ ] `outcome`
  - [ ] if preset resolution fails, the `preset` attribute is omitted
  - [ ] generation ids, fingerprints, artifact identities, paths, config values, env values, and exception messages are forbidden span attributes
- [ ] Metrics:
  - [ ] `kernel.operation_total`
    - [ ] labels exactly `operation|outcome`
  - [ ] `kernel.operation_duration_ms`
    - [ ] labels exactly `operation|outcome`
  - [ ] duration is measured through Foundation `Stopwatch`
  - [ ] duration is emitted as integer milliseconds
  - [ ] allowed outcomes:
    - [ ] `success`
    - [ ] `handled_error`
    - [ ] `failure`
  - [ ] `app_target|preset|generation_id|fingerprint|correlation_id|uow_id` MUST NOT be metric labels
- [ ] Outcome mapping:
  - [ ] successful `OpsResult` → `success`
  - [ ] `OpsResult::outcome() === handled_error` → `handled_error`
  - [ ] thrown exception → `failure`
- [ ] expected outcome classification:
  - [ ] valid validation result → `success`
  - [ ] invalid configuration → `handled_error`
  - [ ] unsupported target or invalid preset selection → `handled_error`
  - [ ] completed cache verification, including `clean|dirty|invalid`, → `success`
  - [ ] unexpected thrown failure → observability `failure` and `KernelOpsFailedException`
- [ ] Logging:
  - [ ] emits at most one safe completion or failure summary
  - [ ] safe fields:
    - [ ] `operation`
    - [ ] `app_target`
    - [ ] resolved `preset`, when available
    - [ ] `outcome`
  - [ ] MAY include `correlation_id|uow_id` when safely available
  - [ ] MUST NOT log raw config, env values, Composer metadata, artifacts, paths, generation ids, fingerprints, exception messages, previous throwables, or stack traces
- [ ] Failure isolation:
  - [ ] tracer, meter, or logger failure MUST NOT alter a successful `OpsResult`
  - [ ] observability failure MUST NOT replace the primary Kernel operation exception
  - [ ] observability failure MUST NOT trigger operation retry

### Security / Result safety (MUST)

- [ ] Kernel Ops results are safe by construction.
- [ ] Every successful or handled-error `OpsResult` is already safe at the `KernelOpsInterface` boundary.
- [ ] `OpsResult` MUST NOT require formatter-side, transport-side, or late redaction to become safe for rendering.
- [ ] CLI defense-in-depth redaction MAY process an `OpsResult` after transport mapping, but MUST NOT be relied upon to remove:
  - [ ] raw Kernel config or env values
  - [ ] Composer metadata
  - [ ] artifact payloads
  - [ ] filesystem paths
  - [ ] Throwable messages or traces
- [ ] `core/kernel` MUST NOT depend on:
  - [ ] `platform/redaction`
  - [ ] `SensitiveDataRedactorInterface`
  - [ ] CLI output or formatter classes
- [ ] `OpsResult` MAY expose only:
  - [ ] stable reason and outcome tokens
  - [ ] canonical operation, target, preset, artifact, and generation identifiers
  - [ ] safe basenames
  - [ ] integer counts and lengths
  - [ ] safe hashes
  - [ ] recursively normalized json-like maps and lists
- [ ] `OpsResult` MUST NOT expose:
  - [ ] raw config or env values
  - [ ] dotenv values
  - [ ] Composer metadata
  - [ ] provider instances or class lists
  - [ ] absolute filesystem paths
  - [ ] relative filesystem paths except `ConfigExplainer`-normalized repo-relative or logical source paths inside `debugConfig().data.explain`
  - [ ] artifact payloads or PHP source
  - [ ] tokens, credentials, headers, cookies, SQL, or arbitrary payloads
  - [ ] Throwable objects, messages, traces, or previous exceptions
- [ ] `KernelOpsFailedException`:
  - [ ] is code-first
  - [ ] exposes only a stable safe reason
  - [ ] MUST NOT include the wrapped Throwable message
  - [ ] MUST NOT include paths, config values, fingerprints, or generation data
- [ ] Safety MUST NOT be configurable:
  - [ ] no config key disables result normalization
  - [ ] no config key enables raw diagnostics
  - [ ] no debug mode exposes unsafe values

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsFacadeDoesNotLeakAbsolutePathsTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsFacadeImplementsContractsPortTest.php`

  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsFacadeReturnsJsonLikeResultsTest.php`
    - [ ] MUST assert deep “json-like” invariants for `OpsResult->data`:
      - [ ] allowed scalar types: null|bool|int|string
      - [ ] arrays only; no objects/resources
      - [ ] floats forbidden (hard-fail)
      - [ ] maps are recursively key-sorted (`strcmp`) by the producer (kernel), lists preserve order

  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsResultIsSafeWithoutLateRedactionTest.php`
    - [ ] covers every successful and handled-error operation result shape
    - [ ] uses raw sensitive fixture values and absolute-path fixtures in lower-level fake inputs
    - [ ] asserts none reaches the returned `OpsResult`
    - [ ] asserts no `SensitiveDataRedactorInterface` service is resolved or invoked
    - [ ] asserts result safety before any CLI formatter or output pipeline is involved

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsDebugConfigPreservesSafeExplainPathsTest.php`
    - [ ] preserves `ConfigExplainer`-normalized repo-relative or logical source paths
    - [ ] rejects absolute filesystem paths
    - [ ] preserves list order and recursively `strcmp`-sorts maps through `OpsResult` normalization

  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsFacadeObservabilityTest.php`
    - [ ] emits exactly one `kernel.operation` span
    - [ ] emits exactly one total metric
    - [ ] emits exactly one duration metric
    - [ ] uses the canonical operation id
    - [ ] labels are limited to `operation|outcome`
    - [ ] no generation id, fingerprint, path, or raw value reaches observability

  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsFailedExceptionIsSafeTest.php`
    - [ ] previous Throwable message is absent
    - [ ] paths and raw values are absent
    - [ ] public code and reason are deterministic

  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsFacadeContextBoundaryTest.php`
    - [ ] reads only:
      - [ ] `ContextKeys::CORRELATION_ID`
      - [ ] `ContextKeys::UOW_ID`
      - [ ] `ContextKeys::UOW_TYPE`
    - [ ] performs no context writes
    - [ ] missing context values do not fail the operation

  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsObservabilityFailureDoesNotChangeOutcomeTest.php`
    - [ ] tracer failure does not change successful result
    - [ ] meter failure does not change successful result
    - [ ] logger failure does not replace the operation result or primary exception

- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsUsesConfiguredTargetPresetTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsDebugModulesDoesNotBuildEnvOrConfigInputsTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsDoesNotSetExplicitBootstrapPresetTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsHostBootsWithoutCurrentGenerationTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsHostUsesConsoleTargetForCommandCompositionTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsOperationsRequireExplicitAppTargetTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsCompilePublishesAndReportsCurrentGenerationTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsHashMatchesCompiledGenerationIdTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsCacheVerifyReportsMissingCurrentAsDirtyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsResultsDoNotExposeSyntheticCurrentGenerationPathsTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsUsesHostSkeletonRootForTargetBootstrapTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsDebugModulesUsesSingleModuleResolutionSnapshotTest.php`

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsCacheVerifyReportsAllFourGenerationFilesTest.php`
    - [ ] result data contains exactly `artifacts|current_generation_id|expected_generation_id|state`
    - [ ] artifact entries contain exactly `basename|existing_byte_count|expected_byte_count|identity|reason|status`
    - [ ] artifact identities match the canonical compile order
    - [ ] no artifact entry contains a path

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsHashReturnsExpectedGenerationIdWithoutWritesTest.php`
    - [ ] result data contains exactly `generation_id`

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsCompileReportsAllFourGenerationFilesTest.php`
    - [ ] result data contains exactly `artifacts|generation_id`
    - [ ] artifact entries contain exactly `basename|identity`
    - [ ] artifact identities and basenames match the canonical order

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsHostRejectsDefinitionOnlyProviderBeforeRegistrationTest.php`
    - [ ] no provider `register()` method is invoked before the capability failure
    - [ ] no partial final definition set is applied

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsHostRejectsInvalidConsoleConfigBeforeFinalProviderRegistrationTest.php`
    - [ ] `ConfigKernel` produces exactly one failed `ConfigValidationResult`
    - [ ] `KernelOpsHostBooter` converts it through `ConfigInvalidException::fromValidationResult(...)`
    - [ ] `ConfigValidator` is not invoked a second time
    - [ ] no final source-host provider is instantiated or registered
    - [ ] no final source operations container is built

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsCompileInvalidConfigDoesNotPublishTest.php`
    - [ ] `ConfigKernel` produces exactly one failed `ConfigValidationResult`
    - [ ] the operation owner converts that existing failed result into `ConfigInvalidException::fromValidationResult(...)`
    - [ ] `ConfigValidator` is not invoked a second time
    - [ ] KernelOpsFacade maps the failure safely
    - [ ] KernelOpsFacade performs no duplicate validation

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsHashInvalidConfigDoesNotBuildGraphTest.php`
    - [ ] `ConfigKernel` produces exactly one failed `ConfigValidationResult`
    - [ ] the operation owner converts that existing failed result into `ConfigInvalidException::fromValidationResult(...)`
    - [ ] `ConfigValidator` is not invoked a second time
    - [ ] KernelOpsFacade maps the failure safely
    - [ ] KernelOpsFacade performs no duplicate validation

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsVerifyInvalidConfigDoesNotReadCurrentTest.php`
    - [ ] `ConfigKernel` produces exactly one failed `ConfigValidationResult`
    - [ ] the operation owner converts that existing failed result into `ConfigInvalidException::fromValidationResult(...)`
    - [ ] `ConfigValidator` is not invoked a second time
    - [ ] KernelOpsFacade maps the failure safely
    - [ ] KernelOpsFacade performs no duplicate validation

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsReusesCanonicalConfigPipelineTest.php`
    - [ ] uses one Bootstrap Phase A resolution
    - [ ] uses one `ModuleResolution`
    - [ ] uses one canonical config-location input set
    - [ ] delegates loading, merge, validation, and explain to existing `ConfigKernel`
    - [ ] KernelOpsFacade performs no package-path or config-file discovery

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsHostComposesEnabledProvidersInCanonicalPlanOrderTest.php`
    - [ ] seed and final source-host builders are distinct instances
    - [ ] seed builder contains only Foundation and Kernel bootstrap providers
    - [ ] final builder receives the validated complete source configuration
    - [ ] exact canonical source values are seeded before provider registration
    - [ ] providers are instantiated in `ContainerProviderPlan` order
    - [ ] every enabled provider implements both `ServiceProviderInterface` and `ContainerDefinitionProviderInterface`
    - [ ] exactly one complete provider batch is submitted
    - [ ] exactly one complete definition set is applied
    - [ ] no imperative-only provider lane exists
    - [ ] external tagged commands are visible through the final `TagRegistry`

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsRunsInsideExistingCallerUowWithoutNestingTest.php`
    - [ ] an arbitrary caller-owned UoW remains the only UoW
    - [ ] the test does not require or instantiate platform/cli
    - [ ] KernelOpsFacade does not call KernelRuntime
    - [ ] KernelOpsFacade does not trigger reset directly

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsCompileUsesSingleModuleResolutionSnapshotTest.php`
    - [ ] `resolveResolution()` is called exactly once
    - [ ] `resolve()` is not called
    - [ ] Composer manifest is read exactly once
    - [ ] the returned `ModuleResolution` is passed unchanged to the operation-specific graph-producing service, and provider planning occurs only inside `RuntimeContainerGraphCompiler`
    - [ ] the same `ModulePlan` instance is supplied to downstream compilation
    - [ ] no second manifest read occurs during provider definition collection
    - [ ] `ArtifactCompiler` does not resolve module services itself

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsHashUsesSingleModuleResolutionSnapshotTest.php`
    - [ ] one module-resolution snapshot is used for the complete hash operation
    - [ ] no second manifest read occurs
    - [ ] `FingerprintCalculator` does not resolve modules itself

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsCacheVerifyUsesSingleModuleResolutionSnapshotTest.php`
    - [ ] `resolveResolution()` is called exactly once
    - [ ] Composer manifest is read exactly once
    - [ ] provider planning consumes the returned snapshot
    - [ ] the same plan is supplied to verification inputs
    - [ ] `CacheVerifier` does not resolve modules itself

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsValidateConfigUsesSingleModuleResolutionSnapshotTest.php`
    - [ ] one `ModuleResolution` is used for the complete validation operation
    - [ ] Composer manifest is read exactly once
    - [ ] the same `ModulePlan` is supplied to `ConfigKernel`
    - [ ] no provider or manifest discovery is repeated

  - [ ] `framework/packages/core/kernel/tests/Integration/KernelOpsDebugConfigUsesSingleModuleResolutionSnapshotTest.php`
    - [ ] one `ModuleResolution` is used for the complete debug operation
    - [ ] Composer manifest is read exactly once
    - [ ] the same `ModulePlan` is supplied to `ConfigKernel`
    - [ ] safe explain output does not expose raw config or env values

- Contract:
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelOpsHasNoRedactionOrCliDependencyContractTest.php`
    - [ ] rejects `SensitiveDataRedactorInterface`
    - [ ] rejects `platform/redaction` package and namespace references
    - [ ] rejects redaction-service resolution
    - [ ] rejects `Coretsia\Platform\*`
    - [ ] rejects CLI formatter and output classes

  - [ ] `framework/packages/core/kernel/tests/Contract/KernelOpsDoesNotDuplicateConfigPipelineContractTest.php`
    - [ ] rejects Kernel Ops-local implementations of:
      - [ ] `ConfigLoaderInterface`
      - [ ] `MergeStrategyInterface`
      - [ ] `ConfigValidatorInterface`
    - [ ] rejects Kernel Ops-local config merger, rules loader, directive processor, validator, and explainer classes
    - [ ] rejects direct `require|include` of package, skeleton, environment, or app config files from `src/Ops`
    - [ ] rejects a second `ConfigRepositoryInterface` implementation
    - [ ] rejects `ConfigSourcePlan` or equivalent parallel config model
    - [ ] allows only orchestration calls into the existing Bootstrap, module, ConfigKernel, artifact, and verification services

  - [ ] `framework/packages/core/kernel/tests/Contract/KernelOpsDoesNotWriteContextOrControlUowContractTest.php`
    - [ ] no direct `ContextStore` write
    - [ ] no `KernelRuntimeInterface` dependency
    - [ ] no `ResetOrchestrator`
    - [ ] no `kernel.reset` discovery

  - [ ] `framework/packages/core/kernel/tests/Contract/KernelOpsFacadeIsCompileHostOnlyContractTest.php`
    - [ ] source container contains `KernelOpsFacade`
    - [ ] source container binds `KernelOpsInterface`
    - [ ] Kernel runtime definitions contain neither service id
    - [ ] generated definition descriptors contain neither service id

  - [ ] `framework/packages/core/kernel/tests/Contract/KernelOperationServicesDoNotResolveModulesContractTest.php`
    - [ ] `ArtifactCompiler` does not depend on module-resolution services
    - [ ] `FingerprintCalculator` does not depend on module-resolution services
    - [ ] `CacheVerifier` does not depend on module-resolution services
    - [ ] none references:
      - [ ] `ModulePlanResolver`
      - [ ] `ManifestReaderInterface`
      - [ ] `ComposerManifestReader`
      - [ ] `ContainerProviderPlanResolver`

### DoD (MUST)

- [ ] `KernelOpsRequest` contains only explicit app target
- [ ] effective preset is resolved exclusively by BootstrapConfigResolver
- [ ] each operation uses one `ModuleResolution`
- [ ] compile returns the actually published generation id
- [ ] hash performs no writes or current read
- [ ] verify reports four artifacts and `clean|dirty|invalid`
- [ ] Ops results contain no raw values or absolute filesystem paths
- [ ] Every `OpsResult` is safe before any CLI formatter or defense-in-depth redactor receives it.
- [ ] Kernel Ops result safety does not depend on late redaction.
- [ ] only `debugConfig().data.explain` may contain `ConfigExplainer`-normalized repo-relative or logical source paths
- [ ] Kernel Ops remains compile-host-only
- [ ] Kernel Ops introduces no config subtree
- [ ] Kernel Ops emits canonical span/metrics through injected ports
- [ ] observability failures never alter operation semantics
- [ ] Kernel Ops reads only safe context values and performs no context writes
- [ ] Kernel Ops never creates nested UoW or triggers reset directly
- [ ] source operations host never submits a mixed declarative/imperative provider batch
- [ ] source operations host rejects invalid console-host configuration before final provider registration
- [ ] source operations host rejects definition-only providers before any provider registration
- [ ] `KernelOpsHostBooter` can be constructed before any container exists
- [ ] Kernel Ops has no CLI, formatter, ANSI, or redaction dependency
- [ ] `OpsResult` outcomes are exactly `success|handled_error`
- [ ] observability outcomes are exactly `success|handled_error|failure`

---

### 2.27.0 Sensitive data redaction boundary (MUST) [CONTRACTS+IMPL+DOC]

---
type: package
phase: 2
epic_id: "2.27.0"
owner_path: "framework/packages/platform/redaction/"

package_id: "platform/redaction"
composer: "coretsia/platform-redaction"
kind: runtime
module_id: "platform.redaction"

goal: "Надати до 2.30.0 єдиний config-free deterministic sensitive-data redaction port і default platform implementation для scalar та json-like diagnostic/output values, з canonical classification, traversal, summary, hashing і fail-closed semantics; producer-owned safe-by-construction shapes залишаються обов’язковими."
provides:
- "Exact contracts-level redaction port for explicitly sensitive scalar values and recursively processed json-like diagnostic/output values."
- "Immutable RedactionContext, RedactionKind, RedactionMode, and RedactedValue contracts with exact deterministic shapes."
- "Default config-free platform redactor with canonical key classification, value classification, traversal precedence, placeholder, byte-length, and domain-separated SHA-256 summary policies."
- "Fixed resource limits and deterministic fail-closed behavior for invalid or unsafe redaction input."
- "One shared SSoT for platform/cli and later runtime diagnostic/output consumers instead of package-local redaction engines or policy registries."
- "Policy: redaction is defense in depth and MUST NOT replace producer-owned safe-by-construction diagnostic shapes."
- "Boundary: Kernel Ops results remain safe by construction and MUST NOT consume this redaction package or port."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-0010-sensitive-data-redaction-boundary.md"
ssot_refs:
- "docs/ssot/sensitive-data-redaction.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/observability.md"
- "docs/ssot/secrets-contracts.md"
- "docs/ssot/config-and-env.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.90.0 — observability and ErrorDescriptor contracts exist for SSoT alignment.
  - 1.100.0 — error and diagnostic safety policy exists.
  - 1.180.0 — contracts secrets port exists for secret-reference policy alignment.
  - 1.200.0 — Foundation declarative DI/container baseline exists.
  - 1.275.0 — Foundation json-like normalization and stable JSON encoding primitives exist.

- Adjacent boundary, not an implementation dependency:
  - 2.25.0 Kernel Ops results remain safe by construction.
  - `core/kernel` MUST NOT consume `SensitiveDataRedactorInterface`.
  - this epic MUST NOT modify Kernel Ops production source or result DTOs.

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Observability/Errors/ErrorDescriptor.php`
  - `framework/packages/core/contracts/src/Secrets/SecretsResolverInterface.php`
  - `framework/packages/core/foundation/src/Serialization/JsonLikeNormalizer.php`
  - `framework/packages/core/foundation/src/Serialization/JsonLikeNormalizationLimits.php`
  - `framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php`
  - `framework/packages/core/foundation/src/Container/ServiceProviderInterface.php`
  - `framework/packages/core/foundation/src/Container/ContainerBuilder.php`
  - `framework/packages/core/foundation/src/Container/Definition/ContainerDefinitionProviderInterface.php`
  - `framework/packages/core/foundation/src/Container/Definition/ContainerDefinitionBuilder.php`
  - `framework/packages/core/foundation/src/Container/Definition/ContainerDefinitionContext.php`
  - `framework/packages/core/foundation/src/Container/Definition/ContainerValueReference.php`

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - defines redaction contracts in `core/contracts`

#### Compile-time deps (deptrac-enforceable) (MUST)

Contracts additions depend on:
- none

`platform/redaction` depends directly on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `core/kernel`
- every other `platform/*` package
- `integrations/*`
- `enterprise/*`
- `devtools/*`
- `psr/log`
- observability ports
- context and UoW ports
- reset orchestration
- vendor HTTP/database/mail/auth/secrets SDK concretes

Required contracts:
- `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`
- `Coretsia\Contracts\Security\RedactionContext`
- `Coretsia\Contracts\Security\RedactionKind`
- `Coretsia\Contracts\Security\RedactionMode`
- `Coretsia\Contracts\Security\RedactedValue`
- `Coretsia\Contracts\Security\Exception\RedactionException`

Required Foundation APIs:
- `Coretsia\Foundation\Serialization\JsonLikeNormalizer`
- `Coretsia\Foundation\Serialization\JsonLikeNormalizationLimits`
- `Coretsia\Foundation\Serialization\StableJsonEncoder`
- declarative Foundation container-definition APIs

`platform/redaction` MUST NOT introduce a logger, tracer, meter, error reporter, context accessor, Kernel runtime, reset orchestrator, or service-locator dependency.

### Entry points / integration points (MUST)

- Runtime DI:
  - when module `platform.redaction` is enabled, its provider contributes exactly one `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` binding;
  - the binding points to `Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor`;
  - the binding is contributed through the canonical dual-interface service provider;
  - the package MUST NOT auto-enable itself from Composer presence, app environment, debug mode, config, or service availability;
  - no service locator or runtime implementation selection exists.

- Module composition:
  - this epic does not modify framework-default mode presets;
  - package-level tests enable `platform.redaction` explicitly through a composed fixture;
  - consumer modules own their dependency edge on `platform.redaction`;
  - 2.30.0 `platform.cli` MUST require module `platform.redaction` through canonical module metadata;
  - absence of a required redaction module MUST fail during module planning rather than during `OutputFormatter` resolution.

- Direct scalar redaction:
  - consumers that already know a scalar value is sensitive call `redactValue(...)` with an explicit `RedactionKind`.
  - callers MUST NOT pass an unclassified known-sensitive scalar through generic value detection.

- Recursive json-like redaction:
  - diagnostic/output boundaries call `redactJsonLike(...)`.
  - key and value classification, recursive traversal, and summary generation remain owned by `platform/redaction`.

- Package owners:
  - producer packages MUST emit safe-by-construction shapes.
  - omission or a stable reason token is preferred when the original value is not operationally necessary.
  - redaction is a final defense-in-depth boundary, not permission to transport arbitrary raw values.

- Kernel Ops:
  - `core/kernel` does not consume this port.
  - `OpsResult` is already safe before CLI receives it.

- Artifacts:
  - generated artifacts MUST NOT rely on runtime redaction as permission to include raw config, env, payload, credential, or secret values.

### Deliverables (MUST)

#### Creates

Contracts:
- [ ] `framework/packages/core/contracts/src/Security/Exception/RedactionException.php`
  - [ ] final contracts-level port failure
  - [ ] extends `RuntimeException`
  - [ ] implements the exact error code, reason allowlist, named constructors, and message contract defined under `Cross-cutting → Errors`
  - [ ] constructor is private
  - [ ] instances are created only through the exact named constructors
  - [ ] exact named constructors:
    - [ ] `public static function inputInvalid(): self`
    - [ ] `public static function inputLimitExceeded(): self`
    - [ ] `public static function sensitiveMapKey(): self`
    - [ ] `public static function outputInvalid(): self`
    - [ ] `public static function internalFailure(): self`
  - [ ] exact accessors:
    - [ ] `public function errorCode(): string`
    - [ ] `public function reason(): string`
  - [ ] stores only the stable reason
  - [ ] does not accept or retain a previous Throwable
  - [ ] contains no rejected value, map key, scope, hash, length, path, pattern, class name, resource id, or payload fragment

- [ ] `framework/packages/core/contracts/src/Security/SensitiveDataRedactorInterface.php`
  - [ ] exact API:
    - [ ] `public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue`
    - [ ] `public function redactJsonLike(mixed $value, RedactionContext $context): mixed`
  - [ ] `redactJsonLike()` return is restricted by PHPDoc to recursively json-like `null|bool|int|string|array`
  - [ ] `redactValue()` requires an explicit owner-selected `RedactionKind`
  - [ ] `redactJsonLike()` uses only the canonical platform key/value classification policy
  - [ ] no callback, mutable policy registry, config argument, logger, observability, ContextStore, or service-locator surface
  - [ ] no platform implementation type appears in the contracts API
  - [ ] both methods declare through PHPDoc:
    - [ ] `@throws Coretsia\Contracts\Security\Exception\RedactionException`
  - [ ] no platform-local exception type appears in the contracts API
  - [ ] neither method writes stdout/stderr
  - [ ] neither method may return an original sensitive scalar from a redacted branch

- [ ] `framework/packages/core/contracts/src/Security/RedactionContext.php`
  - [ ] final readonly value object
  - [ ] `public const int SCHEMA_VERSION = 1`
  - [ ] constructor:
    - [ ] `string $scope`
    - [ ] `RedactionMode $mode = RedactionMode::PLACEHOLDER`
  - [ ] exact accessors:
    - [ ] `schemaVersion(): int`
    - [ ] `scope(): string`
    - [ ] `mode(): RedactionMode`
  - [ ] `scope`:
    - [ ] is a stable operation/output-boundary identifier
    - [ ] matches `\A[a-z][a-z0-9]*(?:[._:-][a-z0-9]+)*\z`
    - [ ] is at most 128 bytes
    - [ ] rejects empty, multiline, NUL, ESC, and control-byte values
  - [ ] invalid scope throws exactly `InvalidArgumentException('redaction-context-scope-invalid')`
  - [ ] constructor validation is limited to the exact syntax, byte bound, and control-byte policy
  - [ ] callers MUST NOT construct scopes from paths, endpoints, user/tenant ids, tokens, field values, request ids, correlation ids, or other high-cardinality runtime data
  - [ ] semantic high-cardinality policy is caller-owned and documented in SSoT; the value object MUST NOT inspect runtime context to infer it
  - [ ] examples of valid scopes: `cli.output`, `logging.record`, `http.problem-detail`
  - [ ] contains no raw redacted value or runtime ContextStore dependency

- [ ] `framework/packages/core/contracts/src/Security/RedactionKind.php`
  - [ ] string-backed enum with exactly:
    - [ ] `UNKNOWN = 'unknown'`
    - [ ] `SECRET = 'secret'`
    - [ ] `SECRET_REFERENCE = 'secret-reference'`
    - [ ] `CREDENTIAL = 'credential'`
    - [ ] `AUTHORIZATION = 'authorization'`
    - [ ] `COOKIE = 'cookie'`
    - [ ] `SESSION_ID = 'session-id'`
    - [ ] `TOKEN = 'token'`
    - [ ] `PAYLOAD = 'payload'`
    - [ ] `SQL = 'sql'`
    - [ ] `PII = 'pii'`
    - [ ] `ENV_VALUE = 'env-value'`
    - [ ] `LOCAL_PATH = 'local-path'`
  - [ ] contains identifiers only
  - [ ] contains no key/value classification, hashing, config, or presentation logic

- [ ] `framework/packages/core/contracts/src/Security/RedactionMode.php`
  - [ ] string-backed enum with exactly:
    - [ ] `PLACEHOLDER = 'placeholder'`
    - [ ] `LENGTH = 'length'`
    - [ ] `HASH = 'hash'`
    - [ ] `HASH_AND_LENGTH = 'hash-and-length'`
  - [ ] `PLACEHOLDER` is the default
  - [ ] `LENGTH|HASH|HASH_AND_LENGTH` require explicit owner selection through `RedactionContext`
  - [ ] contains no `raw|none|disabled|passthrough|debug` mode
  - [ ] debug or environment state MUST NOT change the selected disclosure mode

- [ ] `framework/packages/core/contracts/src/Security/RedactedValue.php`
  - [ ] final readonly value object
  - [ ] `public const int SCHEMA_VERSION = 1`
  - [ ] constructor receives exactly:
    - [ ] `RedactionKind $kind`
    - [ ] `RedactionMode $mode`
    - [ ] `?int $length`
    - [ ] `?string $hash`
  - [ ] exact accessors:
    - [ ] `schemaVersion(): int`
    - [ ] `kind(): RedactionKind`
    - [ ] `mode(): RedactionMode`
    - [ ] `length(): ?int`
    - [ ] `hash(): ?string`
    - [ ] `toArray(): array`
    - [ ] exact PHPDoc:
      - [ ] `@return array{hash: ?string, kind: string, length: ?int, mode: string, redacted: true, schemaVersion: 1}`
  - [ ] `toArray()` returns exactly these `strcmp`-ordered keys:
    - [ ] `hash`
    - [ ] `kind`
    - [ ] `length`
    - [ ] `mode`
    - [ ] `redacted`
    - [ ] `schemaVersion`
  - [ ] exported values:
    - [ ] `redacted = true`
    - [ ] `schemaVersion = 1`
    - [ ] enum fields are exported through their string values
  - [ ] mode invariants:
    - [ ] `placeholder` → `length = null`, `hash = null`
    - [ ] `length` → non-negative `length`, `hash = null`
    - [ ] `hash` → `length = null`, required `hash`
    - [ ] `hash-and-length` → non-negative `length`, required `hash`
  - [ ] hash matches `\Asha256:[a-f0-9]{64}\z`
  - [ ] every invalid constructor combination throws exactly `InvalidArgumentException('redacted-value-shape-invalid')`
  - [ ] negative lengths throw the same fixed exception
  - [ ] malformed hashes throw the same fixed exception
  - [ ] exception messages contain no hash value or other constructor input
  - [ ] MUST NOT retain the raw value, raw bytes, context, path, source metadata, or previous Throwable

Package scaffold:
- [ ] `framework/packages/platform/redaction/composer.json`
  - [ ] `name = coretsia/platform-redaction`
  - [ ] `type = library`
  - [ ] requires exactly:
    - [ ] `php: ^8.4`
    - [ ] `coretsia/core-contracts: ^0.5.0`
    - [ ] `coretsia/core-foundation: ^0.5.0`
  - [ ] MUST NOT require:
    - [ ] `coretsia/core-kernel`
    - [ ] another platform package
    - [ ] `psr/log`
    - [ ] a vendor SDK
  - [ ] PSR-4:
    - [ ] `Coretsia\Platform\Redaction\` → `src/`
  - [ ] autoload-dev:
    - [ ] `Coretsia\Platform\Redaction\Tests\` → `tests/`
  - [ ] `extra.coretsia` exact metadata:
    - [ ] `kind = runtime`
    - [ ] `moduleId = platform.redaction`
    - [ ] `moduleClass = Coretsia\Platform\Redaction\Module\RedactionModule`
    - [ ] `providers = [Coretsia\Platform\Redaction\Provider\RedactionServiceProvider]`
    - [ ] `requires = [core.foundation]`
    - [ ] `conflicts = []`
    - [ ] no `defaultsConfigPath`

- [ ] `framework/packages/platform/redaction/LICENSE`
- [ ] `framework/packages/platform/redaction/NOTICE`
- [ ] `framework/packages/platform/redaction/SECURITY.md`

- [ ] `framework/packages/platform/redaction/README.md`
  - [ ] package purpose and ownership
  - [ ] scalar versus recursive json-like entrypoints
  - [ ] examples use synthetic values only
  - [ ] consumers explicitly construct bounded `RedactionContext`
  - [ ] omission and safe-by-construction output are preferred
  - [ ] redaction is defense in depth
  - [ ] hash and length modes are not declassification
  - [ ] package has no config, disable switch, logger, context, UoW, or reset dependency
  - [ ] points to `docs/ssot/sensitive-data-redaction.md` for canonical policy

Module and provider:
- [ ] `framework/packages/platform/redaction/src/Module/RedactionModule.php`
  - [ ] constants:
    - [ ] `MODULE_ID = 'platform.redaction'`
    - [ ] `PACKAGE_ID = 'platform/redaction'`
    - [ ] `COMPOSER_PACKAGE = 'coretsia/platform-redaction'`
    - [ ] `KIND = 'runtime'`
  - [ ] instance methods:
    - [ ] `id()`
    - [ ] `packageId()`
    - [ ] `composerPackage()`
    - [ ] `kind()`
    - [ ] `providers()`
  - [ ] `providers()` returns only `RedactionServiceProvider::class`
  - [ ] no `CONFIG_ROOT`
  - [ ] no `configRoot()`
  - [ ] no config reads, service resolution, filesystem access, or runtime work

- [ ] `framework/packages/platform/redaction/src/Provider/RedactionServiceProvider.php`
  - [ ] implements `ServiceProviderInterface`
  - [ ] implements `ContainerDefinitionProviderInterface`
  - [ ] `register()` calls `assertDefinitionProviderRegistrationAllowed()`
  - [ ] `register()` delegates through `registerDefinitionProvider($this)`
  - [ ] `define()` is the single wiring source
  - [ ] definitions are declarative and contain no closures or runtime objects
  - [ ] no config root is read
  - [ ] no tags are introduced

Implementation:
- [ ] `framework/packages/platform/redaction/src/Redaction/DefaultSensitiveDataRedactor.php`
  - [ ] implements the exact `SensitiveDataRedactorInterface`
  - [ ] constructor receives exactly:
    - [ ] `SensitiveKeyClassifier`
    - [ ] `SensitiveValueClassifier`
    - [ ] `StableRedactionHasher`
  - [ ] `redactValue()`:
    - [ ] uses the exact input string bytes
    - [ ] rejects values longer than `65536` bytes with `input-limit-exceeded`
    - [ ] `PLACEHOLDER` exposes neither length nor hash
    - [ ] `LENGTH` uses byte-oriented `strlen`
    - [ ] `HASH` delegates to `StableRedactionHasher`
    - [ ] `HASH_AND_LENGTH` exposes both byte length and stable hash
  - [ ] `redactJsonLike()` input stage:
    - [ ] normalizes input through `JsonLikeNormalizer::normalize()`
    - [ ] uses one immutable `JsonLikeNormalizationLimits(32, 10000, 65536)`
    - [ ] max-node semantics are exactly the Foundation normalizer semantics
    - [ ] forbidden input type or structurally invalid input maps to `input-invalid`
    - [ ] input depth/node/string limit violation maps to `input-limit-exceeded`
  - [ ] complete sensitive branch summary materialization:
    - [ ] `PLACEHOLDER` replaces the complete branch without calculating length, hashing, or invoking `StableJsonEncoder`
    - [ ] `LENGTH|HASH|HASH_AND_LENGTH` materialize canonical branch bytes exactly once
    - [ ] a string branch uses its exact string bytes
    - [ ] a non-string branch uses `StableJsonEncoder::encodeStable()`
    - [ ] encoded bytes MUST end in exactly one LF
    - [ ] exactly that final LF is removed
    - [ ] `HASH_AND_LENGTH` reuses the same materialized bytes for both outputs
    - [ ] missing or malformed final-LF output maps to `internal-failure`
    - [ ] encoder failure while representing an input branch maps to `input-invalid`
  - [ ] traversal:
    - [ ] classify structural map key first
    - [ ] a sensitive structural key replaces its complete associated branch with one `RedactedValue::toArray()`
    - [ ] an unclassified key recurses into its value
    - [ ] string leaves are then classified through `SensitiveValueClassifier`
    - [ ] null, bool, and int leaves pass through unchanged unless their owning key is sensitive
    - [ ] list order is preserved
    - [ ] map keys are preserved and maps remain recursively `strcmp` sorted
    - [ ] map keys containing NUL, CR, LF, ESC, or C0 control bytes fail with `input-invalid`
    - [ ] a dynamic map key matching a sensitive-value pattern fails with `sensitive-map-key`
    - [ ] floats, objects, closures, resources, and non-string map keys fail with `input-invalid`
    - [ ] input is never cast, serialized, reflected, inspected, or passed through `__toString()`
    - [ ] JSON, URLs, base64, JWT payloads, SQL, and provider payloads are not decoded or semantically parsed
  - [ ] output stage:
    - [ ] normalizes the completed result with the same fixed limits
    - [ ] fixed limits are private class constants and are not constructor parameters or config values
    - [ ] validates the complete normalized result through `StableJsonEncoder::encodeStable()`
    - [ ] output normalization, output-limit, or final stable-encoding failure maps to `output-invalid`
    - [ ] failure to construct an implementation-generated `RedactedValue` maps to `output-invalid`
    - [ ] returns the normalized value, not the validation JSON bytes
  - [ ] failure handling:
    - [ ] returns neither unchanged input nor a partial result
    - [ ] an existing `Coretsia\Contracts\Security\Exception\RedactionException` propagates unchanged
    - [ ] every other unexpected Throwable becomes safe `internal-failure`
  - [ ] stateless; never retains input, result, current path, classifier decision, or previous failure

- [ ] `framework/packages/platform/redaction/src/Redaction/SensitiveKeyClassifier.php`
  - [ ] stateless
  - [ ] canonical API:
    - [ ] `public function classify(string $key): ?RedactionKind`
  - [ ] classification uses a temporary canonical key:
    - [ ] ASCII lowercase only
    - [ ] removes ASCII `-`, `_`, `.`, and space separators
    - [ ] no locale-sensitive case conversion
    - [ ] no Unicode normalization
  - [ ] original key is never modified
  - [ ] uses only the immutable exact alias table defined in `docs/ssot/sensitive-data-redaction.md`
  - [ ] supported groups are exactly:
    - [ ] `secret-reference`
    - [ ] `secret`
    - [ ] `credential`
    - [ ] `authorization`
    - [ ] `cookie`
    - [ ] `session-id`
    - [ ] `token`
    - [ ] `payload`
    - [ ] `sql`
    - [ ] `pii`
    - [ ] `env-value`
    - [ ] `local-path`
  - [ ] unknown keys return `null`
  - [ ] no config, env, mutable registry, learned state, or runtime regex loading

- [ ] `framework/packages/platform/redaction/src/Redaction/SensitiveValueClassifier.php`
  - [ ] stateless
  - [ ] canonical API:
    - [ ] `public function classify(string $value): ?RedactionKind`
  - [ ] exact precedence:
    - [ ] `authorization`
    - [ ] `cookie`
    - [ ] `credential`
    - [ ] `token`
    - [ ] `sql`
    - [ ] `local-path`
    - [ ] `pii`
  - [ ] unkeyed values are never automatically classified as:
    - [ ] `secret`
    - [ ] `secret-reference`
    - [ ] `session-id`
    - [ ] `payload`
    - [ ] `env-value`
  - [ ] those kinds require either a sensitive structural key or explicit `redactValue()` owner classification
  - [ ] baseline high-confidence recognition covers exactly:
    - [ ] complete `Basic` or `Bearer` authorization values
    - [ ] complete `Cookie:` or `Set-Cookie:` header-like values
    - [ ] credential-bearing URI/DSN values
    - [ ] JWT-shaped three-segment tokens
    - [ ] AWS access-key-shaped `AKIA|ASIA` values
    - [ ] prefixed token values beginning exactly with `sk_|tok_|token_` followed by `8..512` ASCII characters from `[A-Za-z0-9_-]`
    - [ ] SQL values beginning with the SSoT-defined operation-prefix set
    - [ ] Unix, Windows-drive, and UNC absolute paths
    - [ ] email-address-shaped values
  - [ ] unclassified values return `null`
  - [ ] MUST NOT use entropy scoring or classify every long string as a token
  - [ ] MUST NOT classify arbitrary numeric strings as phone numbers
  - [ ] MUST NOT perform network calls, read config/env, decode payloads, or load mutable patterns
  - [ ] no locale-dependent classification

- [ ] `framework/packages/platform/redaction/src/Redaction/StableRedactionHasher.php`
  - [ ] stateless
  - [ ] canonical API:
    - [ ] `public function hash(string $bytes, RedactionKind $kind, RedactionContext $context): string`
  - [ ] SHA-256 input is exactly:
    - [ ] `"coretsia.redaction@1\0" . $context->scope() . "\0" . $kind->value . "\0" . $bytes`
  - [ ] output is exactly `sha256:` followed by 64 lowercase hexadecimal characters
  - [ ] same scope, kind, and bytes always produce the same hash
  - [ ] changing scope or kind changes the hash domain
  - [ ] no salt, timestamp, hostname, process id, random bytes, env value, or machine-specific input participates
  - [ ] hashing MUST NOT be documented as encryption or proof that low-entropy input is non-sensitive

Docs:
- [ ] `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
  - [ ] records one contracts port plus one default platform implementation
  - [ ] records config-free, stateless, fail-closed policy
  - [ ] records placeholder as the default disclosure mode
  - [ ] records explicit owner selection for length/hash summaries
  - [ ] records domain-separated SHA-256
  - [ ] records that redaction does not replace safe-by-construction producer shapes
  - [ ] rejects:
    - [ ] package-local redaction engines
    - [ ] config-driven classifiers
    - [ ] mutable policy registries
    - [ ] raw/debug/passthrough modes
    - [ ] redactor-owned observability
    - [ ] semantic payload parsing

- [ ] `docs/ssot/sensitive-data-redaction.md`
  - [ ] exact public contract signatures and immutable result shapes
  - [ ] exact contracts-level failure type `Coretsia\Contracts\Security\Exception\RedactionException`
  - [ ] exact error code, reason allowlist, private construction, and named constructors
  - [ ] exact `RedactionKind` and `RedactionMode` values
  - [ ] exact `RedactionContext` scope syntax and bounds
  - [ ] exact `RedactedValue::toArray()` shape and mode invariants
  - [ ] exact key canonicalization procedure
  - [ ] exact immutable key alias table:
    - [ ] `secret-reference = secretref|secretreference|keyref`
    - [ ] `secret = secret|secretvalue|password|passwd|pwd|clientsecret|privatekey|secretkey`
    - [ ] `credential = credential|credentials|dsn|connectionstring`
    - [ ] `authorization = authorization|proxyauthorization`
    - [ ] `cookie = cookie|cookies|setcookie`
    - [ ] `session-id = session|sessionid`
    - [ ] `token = token|accesstoken|refreshtoken|idtoken|bearertoken|apikey|xapikey|accesskey|csrf|csrftoken|xsrf|xsrftoken`
    - [ ] `payload = payload|body|requestbody|responsebody`
    - [ ] `sql = sql|query|bindings`
    - [ ] `pii = email|emailaddress|phone|phonenumber|firstname|lastname|fullname|address|dateofbirth|birthdate|dob`
    - [ ] `env-value = env|environment|envvalue|dotenv`
    - [ ] `local-path = path|filepath|absolutepath|directory|workingdirectory|cwd`
  - [ ] exact value-classifier precedence and deterministic pattern definitions
  - [ ] value patterns are fully anchored and ASCII-defined
  - [ ] exact value grammars:
    - [ ] authorization:
      - [ ] ASCII case-insensitive `Basic|Bearer`
      - [ ] followed by one or more ASCII space/tab bytes
      - [ ] followed by a non-empty value containing no CR/LF
    - [ ] cookie:
      - [ ] ASCII case-insensitive `Cookie:|Set-Cookie:`
      - [ ] followed by optional ASCII space/tab bytes
      - [ ] followed by a non-empty value containing no CR/LF
    - [ ] credential-bearing URI:
      - [ ] ASCII scheme matching `[A-Za-z][A-Za-z0-9+.-]*`
      - [ ] followed by `://`
      - [ ] non-empty user component
      - [ ] `:`
      - [ ] non-empty password component
      - [ ] `@`
    - [ ] JWT:
      - [ ] exactly three non-empty base64url-shaped segments separated by `.`
    - [ ] AWS access key:
      - [ ] exact prefix `AKIA|ASIA`
      - [ ] followed by exactly 16 uppercase ASCII alphanumeric characters
    - [ ] prefixed token:
      - [ ] exact prefix `sk_|tok_|token_`
      - [ ] followed by `8..512` ASCII `[A-Za-z0-9_-]` characters
    - [ ] SQL:
      - [ ] optional leading ASCII space/tab bytes
      - [ ] ASCII case-insensitive first token from `SELECT|INSERT|UPDATE|DELETE|MERGE|REPLACE|ALTER|CREATE|DROP|TRUNCATE|GRANT|REVOKE|CALL|EXEC|EXECUTE`
      - [ ] followed by a token boundary
    - [ ] local path:
      - [ ] Unix absolute path beginning `/`
      - [ ] Windows drive path beginning `[A-Za-z]:\` or `[A-Za-z]:/`
      - [ ] UNC path beginning `\\`
    - [ ] email:
      - [ ] exactly one `@`
      - [ ] non-empty ASCII local part
      - [ ] domain contains at least one `.`
      - [ ] domain labels are non-empty and do not begin or end with `-`
  - [ ] near-miss strings remain unclassified
  - [ ] exact key-first recursive traversal algorithm
  - [ ] sensitive dynamic map-key failure policy
  - [ ] exact byte-length and stable JSON branch-summary rules
  - [ ] `PLACEHOLDER` performs no branch-byte materialization or hashing
  - [ ] `LENGTH|HASH|HASH_AND_LENGTH` materialize canonical branch bytes exactly once
  - [ ] exact domain-separated SHA-256 input and output
  - [ ] fixed limits:
    - [ ] depth `32`
    - [ ] nodes `10000`
    - [ ] string bytes `65536`
  - [ ] exact fail-closed reason mapping
  - [ ] exact allowed redacted summary fields
  - [ ] explicit prohibition of decoding or semantically parsing payloads
  - [ ] omission-first and safe-by-construction producer policy
  - [ ] warning that hash and length summaries may remain sensitive metadata

#### Modifies

- [ ] `framework/packages/core/contracts/README.md`
  - [ ] document the Security redaction contracts
  - [ ] document exact scalar and json-like entrypoints
  - [ ] clarify that the contracts package owns no implementation or classifier policy

- [ ] `docs/ssot/INDEX.md`
  - [ ] register `docs/ssot/sensitive-data-redaction.md`

- [ ] `docs/adr/INDEX.md`
  - [ ] register `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`

- [ ] `docs/ssot/observability-and-errors.md`
  - [ ] add the shared redaction mechanism
  - [ ] preserve safe-by-construction as the primary requirement
  - [ ] forbid relying on late redaction to legitimize unsafe diagnostic shapes
  - [ ] allow only safe fields or canonical redacted summaries in error descriptors, reporter payloads, and diagnostic extensions

- [ ] `docs/ssot/observability.md`
  - [ ] logs and spans may contain only safe values or canonical redacted summaries
  - [ ] metrics remain allowlist-only
  - [ ] redaction MUST NOT permit arbitrary metric labels
  - [ ] raw payloads, headers, cookies, tokens, SQL, env values, provider payloads, and absolute paths remain forbidden span attributes
  - [ ] record that `platform/redaction` emits no baseline logs, spans, or metrics

- [ ] `docs/ssot/secrets-contracts.md`
  - [ ] resolved secrets are never diagnostic-safe
  - [ ] raw secret references remain sensitive metadata
  - [ ] omission is preferred
  - [ ] where a summary is unavoidable, consumers use the shared redaction port with explicit kind and context

- [ ] `docs/ssot/config-and-env.md`
  - [ ] raw env values MUST NOT reach diagnostics
  - [ ] explain/source traces may expose only safe provenance metadata
  - [ ] redaction does not permit raw configuration trees or env dumps
  - [ ] omission is preferred when a summary is unnecessary

- [ ] repo-root `composer.json`
  - [ ] include `coretsia/platform-redaction` in canonical generated path-package version metadata

- [ ] `framework/composer.json`
  - [ ] include `coretsia/platform-redaction: 0.5.x-dev` in workspace `require-dev`
  - [ ] include the package in canonical generated path-package version metadata

- [ ] `framework/tools/testing/package-index.php`
  - [ ] regenerate through the canonical package-index generator

- [ ] `framework/tools/testing/deptrac.yaml`
  - [ ] regenerate to include `packages/platform/redaction/src`
  - [ ] enforce only the allowed `core/contracts` and `core/foundation` edges

#### Configuration (keys + defaults)

This package introduces no config root and no config files.

The following files MUST NOT exist:

```text
framework/packages/platform/redaction/config/redaction.php
framework/packages/platform/redaction/config/rules.php
```

`RedactionModule` has no `CONFIG_ROOT` and no `configRoot()` method.

The following keys and equivalent aliases are forbidden:

```text
redaction.enabled
redaction.mode
redaction.disable
redaction.policy
redaction.patterns
redaction.hash_algorithm
security.redaction.enabled
foundation.redaction.enabled
cli.redaction.enabled
```

Rules:
- the redaction boundary cannot be disabled
- the default mode is selected explicitly by `RedactionContext`, not global config
- classifier vocabulary and precedence are SSoT-owned code policy
- no runtime-regex registry is loaded from config
- no env variable alters redaction behavior
- no debug/app environment alters redaction behavior
- future extensibility or custom policy packs require a separate epic

#### Wiring / DI tags (when applicable)

Tags introduced:
- none

`RedactionServiceProvider::define()` contributes in this exact order:

```text
SensitiveKeyClassifier
SensitiveValueClassifier
StableRedactionHasher
DefaultSensitiveDataRedactor
SensitiveDataRedactorInterface alias
```

Exact declarative wiring:

- [ ] class service:
  - [ ] `Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier`
- [ ] class service:
  - [ ] `Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier`
- [ ] class service:
  - [ ] `Coretsia\Platform\Redaction\Redaction\StableRedactionHasher`
- [ ] class service:
  - [ ] id and class: `Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor`
  - [ ] constructor service references, in order:
    - [ ] `SensitiveKeyClassifier`
    - [ ] `SensitiveValueClassifier`
    - [ ] `StableRedactionHasher`
- [ ] alias:
  - [ ] `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`
  - [ ] → `Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor`

Additional rules:

- when `RedactionServiceProvider` is applied for an enabled `platform.redaction` module, exactly one `SensitiveDataRedactorInterface` binding exists
- when the module is not enabled, the provider is not applied and the package contributes no redactor binding
- no factory service is required
- no closures exist in canonical definitions
- no config parameters exist
- no tags exist
- all package services are shared
- all registered services are stateless and safe to share
- source registration and declarative definitions are semantically identical

#### Artifacts / outputs (if applicable)

N/A.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Redaction services read no `ContextStore` values.
- [ ] Redaction services write no context values.
- [ ] Redaction services do not receive `ContextAccessorInterface`.
- [ ] `RedactionContext` is an explicit immutable method argument and is unrelated to runtime `ContextStore`.
- [ ] Redaction services do not create or participate in a Kernel UoW.
- [ ] Redaction services do not receive `KernelRuntimeInterface`.
- [ ] `DefaultSensitiveDataRedactor`, both classifiers, and the hasher are stateless.
- [ ] No service implements `ResetInterface`.
- [ ] No service is tagged `kernel.stateful`.
- [ ] No service is tagged `kernel.reset`.
- [ ] No static mutable cache, learned classifier state, or previous-input state exists.
- [ ] Any future stateful/custom policy capability requires a separate epic.

#### Observability

- [ ] No spans are emitted.
- [ ] No metrics are emitted.
- [ ] No logs are emitted.
- [ ] No observability port is injected.
- [ ] No logger is injected.
- [ ] Redaction input, classification decisions, hashes, lengths, kinds, scopes, failures, and rejected values are not self-observed.
- [ ] The caller MAY observe only its own already-safe operation outcome under the caller-owned observability policy.
- [ ] A redaction failure throws `RedactionException`; the redactor does not log and does not return a fallback.

#### Errors

- [ ] `Coretsia\Contracts\Security\Exception\RedactionException`
  - [ ] extends `RuntimeException`
  - [ ] constructor is private
  - [ ] construction is allowed only through the exact named constructors
  - [ ] error code: `CORETSIA_REDACTION_FAILED`
  - [ ] exact public message:
    - [ ] `CORETSIA_REDACTION_FAILED: <reason>`
  - [ ] exposes:
    - [ ] `errorCode(): string`
    - [ ] `reason(): string`
  - [ ] allows only:
    - [ ] `input-invalid`
    - [ ] `input-limit-exceeded`
    - [ ] `sensitive-map-key`
    - [ ] `output-invalid`
    - [ ] `internal-failure`
  - [ ] named constructors:
    - [ ] `public static function inputInvalid(): self`
    - [ ] `public static function inputLimitExceeded(): self`
    - [ ] `public static function sensitiveMapKey(): self`
    - [ ] `public static function outputInvalid(): self`
    - [ ] `public static function internalFailure(): self`
  - [ ] does not accept or retain a previous Throwable
  - [ ] contains no raw value, map key, scope, hash, length, pattern, path, class name, resource id, or payload fragment

Exception mapping:

- input stage:
  - unsupported input type or structurally invalid json-like input → `input-invalid`
  - stable encoding failure while materializing a normalized input branch → `input-invalid`
  - input normalization depth/node/string limit violation → `input-limit-exceeded`
  - direct `redactValue()` input over `65536` bytes → `input-limit-exceeded`

- traversal stage:
  - sensitive dynamic map key → `sensitive-map-key`

- output stage:
  - invalid implementation-generated `RedactedValue` → `output-invalid`
  - output normalization or output-limit violation → `output-invalid`
  - final stable-encoding validation failure → `output-invalid`

- implementation invariant:
  - missing or malformed encoder-owned final LF → `internal-failure`
  - any other unexpected implementation failure → `internal-failure`

The implementation MUST rethrow an existing `Coretsia\Contracts\Security\Exception\RedactionException` unchanged.

Every other caught Throwable is converted to `internalFailure()` without copying or retaining the original Throwable.

#### Security / Redaction

- [ ] Redaction is defense in depth, not source-data authorization.
- [ ] Producer-owned safe-by-construction shapes remain mandatory.
- [ ] Omission is preferred when no summary is required.
- [ ] Placeholder-only is the baseline default.
- [ ] Length and hash disclosure require explicit `RedactionContext` mode.
- [ ] Hashes and lengths remain potentially sensitive correlation metadata.
- [ ] No output mode returns raw sensitive input.
- [ ] No failure returns unchanged or partially redacted input.
- [ ] No raw input appears in exceptions, logs, spans, metrics, diagnostics, or provider definitions.

The package MUST NOT leak:
- resolved secrets
- raw secret references by default
- credentials
- authorization values
- cookies
- session ids
- tokens
- raw request or response payloads
- raw SQL or bindings
- environment values
- credential-bearing DSNs
- absolute local paths
- provider payloads
- PII-like values
- dynamic sensitive map keys

Allowed redacted summary fields are exactly, in byte-order `strcmp` key order:

```text
hash
kind
length
mode
redacted
schemaVersion
```

No timestamp, host, process id, request id, user id, tenant id, path, original field value, preview, prefix, suffix, or partially masked raw value is allowed.

### Canonical fixture matrix (MUST)

Tests use only synthetic values.

Key-classification fixtures:

```text
password            -> secret
secret_ref          -> secret-reference
clientSecret        -> secret
credentials         -> credential
Authorization       -> authorization
proxy-authorization -> authorization
Cookie               -> cookie
session_id           -> session-id
access_token         -> token
x-api-key            -> token
request_body         -> payload
sql                  -> sql
bindings             -> sql
email_address        -> pii
env_value            -> env-value
absolute_path        -> local-path
```

Value-classification fixtures:

```text
Bearer synthetic-token-value
-> authorization

Cookie: session=synthetic-session
-> cookie

mysql://synthetic-user:synthetic-password@example.test/database
-> credential

eyJhbGciOiJub25lIn0.eyJzdWIiOiJzeW50aGV0aWMifQ.signature
-> token

AKIA1234567890ABCDEF
-> token

tok_synthetic_example
-> token

SELECT * FROM synthetic_users WHERE email = ?
-> sql

/home/synthetic/project/.env
-> local-path

C:\synthetic\project\.env
-> local-path

synthetic-user@example.test
-> pii
```

Non-sensitive controls:

```text
success
handled-error
module-id
artifact-generation
42
true
null
relative/safe-logical-id
```

Required assertions:

- every sensitive fixture is absent from redacted output
- every sensitive fixture is absent from exception messages
- key classification wins over nested traversal
- non-sensitive controls remain unchanged
- lists preserve order
- maps are recursively `strcmp` sorted
- repeated calls produce identical bytes and shapes
- placeholder mode exposes neither length nor hash
- length mode exposes byte length only
- hash mode exposes domain-separated hash only
- hash-and-length exposes both
- changing scope changes the hash
- changing kind changes the hash
- the same scope, kind, and bytes produce the same hash
- raw fixtures do not appear in logs, spans, metrics, or diagnostics because the package emits none

### Tests (MUST)

- Contracts:
  - [ ] `framework/packages/core/contracts/tests/Contract/SensitiveDataRedactorInterfaceShapeContractTest.php`
    - [ ] exact two public methods
    - [ ] exact parameter and return types
    - [ ] both methods document `Coretsia\Contracts\Security\Exception\RedactionException`
    - [ ] no platform-local exception appears in the interface contract
    - [ ] no implementation-package dependency

  - [ ] `framework/packages/core/contracts/tests/Contract/RedactionExceptionShapeContractTest.php`
    - [ ] exact FQCN belongs to `core/contracts`
    - [ ] exact error code is `CORETSIA_REDACTION_FAILED`
    - [ ] exact public message is `CORETSIA_REDACTION_FAILED: <reason>`
    - [ ] constructor is private
    - [ ] every named constructor maps to exactly one allowlisted reason
    - [ ] unknown reasons cannot be constructed
    - [ ] native exception code remains `0`
    - [ ] no previous Throwable is retained
    - [ ] no raw fixture value appears in the message

  - [ ] `framework/packages/core/contracts/tests/Contract/RedactionContextShapeContractTest.php`
    - [ ] exact fields and accessors
    - [ ] default placeholder mode
    - [ ] scope regex and byte bound
    - [ ] invalid syntax, excessive byte length, whitespace, multiline, NUL, ESC, and control bytes throw exactly `redaction-context-scope-invalid`
    - [ ] documents that runtime/high-cardinality semantic values are forbidden caller inputs but are not inferred by the value object

  - [ ] `framework/packages/core/contracts/tests/Contract/RedactionEnumsContractTest.php`
    - [ ] exact `RedactionKind` cases and values
    - [ ] exact `RedactionMode` cases and values
    - [ ] no raw/disabled mode

  - [ ] `framework/packages/core/contracts/tests/Contract/RedactedValueShapeContractTest.php`
    - [ ] exact six-key `toArray()` shape
    - [ ] recursive json-like compatibility
    - [ ] exact mode invariants
    - [ ] every invalid mode/length/hash combination throws exactly `redacted-value-shape-invalid`
    - [ ] malformed hash and negative-length inputs are absent from exception messages
    - [ ] no original value storage

- Package contracts:
  - [ ] `framework/packages/platform/redaction/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
    - [ ] module and provider are loadable
    - [ ] module metadata matches composer metadata
    - [ ] module has no config root
    - [ ] provider construction has no side effects

  - [ ] `framework/packages/platform/redaction/tests/Contract/RedactionModuleComposerMetadataContractTest.php`
    - [ ] exact package name, namespace, module id, provider, requires, and conflicts
    - [ ] no `defaultsConfigPath`
    - [ ] exact direct Composer dependencies

  - [ ] `framework/packages/platform/redaction/tests/Contract/RedactionProviderDefinitionsContainNoClosuresContractTest.php`
    - [ ] exact service-definition order
    - [ ] no closures or runtime objects
    - [ ] exact interface alias
    - [ ] no config parameters or tags

  - [ ] `framework/packages/platform/redaction/tests/Contract/RedactionProviderSourceDefinitionsParityTest.php`
    - [ ] source-mode registration and declarative definitions resolve the same services
    - [ ] applying the provider produces exactly one redactor binding
    - [ ] source and declarative application produce the same binding

  - [ ] `framework/packages/platform/redaction/tests/Contract/RedactionPackageHasNoConfigSurfaceContractTest.php`
    - [ ] no config directory
    - [ ] no config root in module metadata
    - [ ] no disable or runtime policy keys
    - [ ] no env reads

  - [ ] `framework/packages/platform/redaction/tests/Contract/RedactionRuntimeHasNoContextObservabilityOrResetDependencyContractTest.php`
    - [ ] no ContextStore or ContextAccessor
    - [ ] no KernelRuntimeInterface
    - [ ] no ResetInterface
    - [ ] no reset/stateful tags
    - [ ] no logger, tracer, meter, or reporter
    - [ ] no stdout/stderr writes

  - [ ] `framework/packages/platform/redaction/tests/Contract/RedactionDoesNotExposeRawValuesContractTest.php`
    - [ ] complete canonical fixture matrix
    - [ ] output and exception messages contain no raw fixture value

  - [ ] `framework/packages/platform/redaction/tests/Contract/RedactionOutputIsDeterministicContractTest.php`
    - [ ] same input/context produces the same recursively normalized result
    - [ ] map order is canonical
    - [ ] list order is preserved
    - [ ] hash output is stable

- Unit:
  - [ ] `framework/packages/platform/redaction/tests/Unit/SensitiveKeyClassifierTest.php`
    - [ ] exact canonicalization
    - [ ] exact key vocabulary
    - [ ] every alias listed in `docs/ssot/sensitive-data-redaction.md` is covered
    - [ ] aliases map to exactly the documented `RedactionKind`
    - [ ] case and separator variants
    - [ ] no locale dependence
    - [ ] unknown keys return null

  - [ ] `framework/packages/platform/redaction/tests/Unit/SensitiveValueClassifierTest.php`
    - [ ] exact high-confidence patterns
    - [ ] covers every SSoT-defined baseline pattern class
    - [ ] precedence collisions resolve according to the exact documented order
    - [ ] exact precedence
    - [ ] non-sensitive controls remain unclassified
    - [ ] no entropy or broad long-string heuristic
    - [ ] near-miss fixtures for every pattern remain unclassified
    - [ ] no automatic unkeyed classification exists for `secret|secret-reference|session-id|payload|env-value`

  - [ ] `framework/packages/platform/redaction/tests/Unit/StableRedactionHasherTest.php`
    - [ ] exact domain-separated input
    - [ ] exact NUL separators and `coretsia.redaction@1` prefix
    - [ ] direct scalar bytes are hashed unchanged
    - [ ] no salt, time, host, process, env, or random input participates
    - [ ] exact `sha256:<64-lower-hex>` output
    - [ ] scope and kind separation
    - [ ] byte-oriented behavior

  - [ ] `framework/packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorValueTest.php`
    - [ ] explicit scalar redaction for every kind
    - [ ] no raw scalar is retained or returned

  - [ ] `framework/packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorJsonLikeTraversalTest.php`
    - [ ] key-first branch redaction
    - [ ] one sensitive key replaces its complete value branch with one redacted summary
    - [ ] complete non-string branch summaries use normalized stable JSON without the encoder-owned final LF
    - [ ] recursive value classification
    - [ ] list preservation
    - [ ] canonical map ordering
    - [ ] non-sensitive scalar preservation
    - [ ] no JSON, URL, base64, JWT payload, SQL, or provider-payload decoding occurs

  - [ ] `framework/packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorModesTest.php`
    - [ ] exact placeholder, length, hash, and hash-and-length shapes
    - [ ] placeholder mode does not encode or hash a complete sensitive branch
    - [ ] a synthetic non-string sensitive branch containing malformed UTF-8 is safely replaced in placeholder mode
    - [ ] the same branch fails with `input-invalid` when length or hash requires stable JSON byte materialization
    - [ ] hash-and-length materializes and hashes one canonical byte representation

  - [ ] `framework/packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorRejectsSensitiveMapKeysTest.php`
    - [ ] sensitive dynamic map key fails closed
    - [ ] key value is absent from exception diagnostics

  - [ ] `framework/packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorLimitsTest.php`
    - [ ] exact depth, node, and string-byte limits
    - [ ] limit failures expose only the stable reason
    - [ ] direct `redactValue()` input over `65536` bytes fails with `input-limit-exceeded`
    - [ ] output expansion beyond the fixed output budget fails with `output-invalid`

  - [ ] `framework/packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorFailureStageMappingTest.php`
    - [ ] unsupported input type → `input-invalid`
    - [ ] input-branch stable encoding failure → `input-invalid`
    - [ ] input normalization limit → `input-limit-exceeded`
    - [ ] sensitive dynamic map key → `sensitive-map-key`
    - [ ] invalid implementation-generated `RedactedValue` → `output-invalid`
    - [ ] output normalization or output-limit failure → `output-invalid`
    - [ ] final stable-encoding validation failure → `output-invalid`
    - [ ] missing or malformed encoder-owned final LF → `internal-failure`
    - [ ] unexpected implementation failure → `internal-failure`
    - [ ] existing `RedactionException` is rethrown as the same instance
    - [ ] no failure returns unchanged input or a partial result

  - [ ] `framework/packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorFailsClosedTest.php`
    - [ ] rejects floats, objects, closures, resources, and non-string map keys
    - [ ] map keys containing NUL, CR, LF, ESC, or another C0 control byte fail with `input-invalid`
    - [ ] rejected map keys are absent from exception messages
    - [ ] returns no unchanged input or partial result
    - [ ] preserves no previous Throwable

- Integration:
  - [ ] `framework/packages/platform/redaction/tests/Integration/RedactionServiceProviderWiresDefaultRedactorTest.php`
    - [ ] canonical source container resolves `SensitiveDataRedactorInterface`
    - [ ] resolved service is `DefaultSensitiveDataRedactor`
    - [ ] classifiers and hasher are injected in exact order
    - [ ] repeated resolution returns the same shared stateless service
    - [ ] redaction works without config, context, Kernel runtime, or observability services

  - [ ] `framework/packages/platform/redaction/tests/Integration/RedactionModuleRequiresExplicitEnablementTest.php`
    - [ ] an installed but disabled package contributes no provider or binding
    - [ ] an explicitly enabled `platform.redaction` module contributes exactly one binding
    - [ ] no config, debug, app environment, or Composer-presence auto-enablement occurs

- Gates / architecture:
  - [ ] package index regenerated and green
  - [ ] deptrac generated and green
  - [ ] package compliance green
  - [ ] package scaffold check green
  - [ ] package PHPUnit configuration gate green
  - [ ] contracts-only ports gate green
  - [ ] ECS and PHPStan green

### DoD (MUST)

- [ ] `SensitiveDataRedactorInterface` exposes exactly `redactValue()` and `redactJsonLike()`.
- [ ] The public redaction failure type belongs to `core/contracts`; consumers need no dependency on the concrete platform implementation to catch redaction failures.
- [ ] `RedactionKind` and `RedactionMode` contain exactly the canonical enum values.
- [ ] `RedactionContext` has exact bounded scope and mode semantics.
- [ ] `RedactedValue` has the exact deterministic six-key exported shape.
- [ ] No mode or config option returns raw sensitive input.
- [ ] Placeholder is the default mode.
- [ ] Length is byte length.
- [ ] Hashing uses the exact domain-separated SHA-256 contract.
- [ ] Recursive traversal uses exact key-first classification precedence.
- [ ] Lists preserve order and maps are recursively `strcmp` sorted.
- [ ] Fixed depth, node, and string-byte limits are enforced.
- [ ] Invalid input and internal failures fail closed.
- [ ] No complete or partial original value is returned after failure.
- [ ] Sensitive dynamic map keys fail deterministically.
- [ ] Every `RedactionException` exposes only `CORETSIA_REDACTION_FAILED` and an allowlisted reason.
- [ ] `RedactionContext` invariant violations expose only `InvalidArgumentException('redaction-context-scope-invalid')`.
- [ ] `RedactedValue` invariant violations expose only `InvalidArgumentException('redacted-value-shape-invalid')`.
- [ ] No exception message contains rejected constructor input, redaction input, field keys, hashes, paths, payload fragments, or previous Throwable messages.
- [ ] Runtime services are stateless and shared.
- [ ] No ContextStore, UoW, reset, logging, tracing, metrics, or reporter dependency exists.
- [ ] No config root, config files, disable toggle, runtime pattern registry, or env-controlled behavior exists.
- [ ] `RedactionModule` and Composer metadata match exactly.
- [ ] Composer requires only PHP, `core/contracts`, and `core/foundation`.
- [ ] An enabled `platform.redaction` module contributes exactly one `SensitiveDataRedactorInterface` binding to the default implementation.
- [ ] An installed but disabled module contributes no provider and no redactor binding.
- [ ] Provider source registration and declarative definitions are semantically identical.
- [ ] Redaction does not replace producer-owned safe-by-construction diagnostic shapes.
- [ ] Kernel Ops remains independent from `platform/redaction`.
- [ ] No Phase 3–6 production package is modified by this epic.
- [ ] No future roadmap epic is rewritten as an implementation deliverable of this package.
- [ ] Docs updated:
  - [ ] `framework/packages/core/contracts/README.md`
  - [ ] `framework/packages/platform/redaction/README.md`
  - [ ] `framework/packages/platform/redaction/SECURITY.md`
  - [ ] `docs/ssot/sensitive-data-redaction.md`
  - [ ] `docs/ssot/observability-and-errors.md`
  - [ ] `docs/ssot/observability.md`
  - [ ] `docs/ssot/secrets-contracts.md`
  - [ ] `docs/ssot/config-and-env.md`
  - [ ] `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
- [ ] All contract, unit, integration, architecture, package, ECS, and PHPStan checks pass.
- [ ] Non-goals remain:
  - [ ] secret resolution
  - [ ] Vault/AWS/GCP integrations
  - [ ] request-body inspection or semantic payload parsing
  - [ ] SQL parsing
  - [ ] mail-body rewriting
  - [ ] AI guardrail or PII rewriting
  - [ ] configurable/custom policy packs
  - [ ] stateful classifier learning
  - [ ] partially masked previews

---

### 2.30.0 Platform CLI — Tag-first Command Catalog + Kernel ops consumption (MUST) [IMPL]

---
type: package
phase: 2
epic_id: "2.30.0"
owner_path: "framework/packages/platform/cli/"

package_id: "platform/cli"
composer: "coretsia/platform-cli"
kind: runtime
module_id: "platform.cli"

goal: "Переписати platform/cli як tag-first source-host command runtime: детерміновано відкривати команди enabled packages, виконувати normal commands через один Kernel UoW і надавати Kernel-owned config/module/cache operations через KernelOpsInterface з preset, визначеним Bootstrap configuration."
provides:
- "Command discovery SSoT: DI tag `cli.command` + deterministic tag order from Foundation TagRegistry"
- 'Kernel ops consumption: config validate/debug/compile/hash, module debug, and cache verify via `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`'
- "Explicit app-target selection for every Kernel operation command"
- "Generation-aware compile/hash/verify rendering without direct artifact reads"
- "Exact command argument/option metadata schema"
- "Descriptor-driven structural input validation before command service resolution"
- "Per-invocation buffered command output followed by one deterministic render"
- "Effective preset rendering from Kernel-owned OpsResult"
- "Safe output (deterministic JSON/table/plain) + redaction (no secrets/PII)"
- "Reserved names implemented (`help`, `list`) and enforced"
- "Package-agnostic command discovery: any enabled package may contribute lazy `cli.command` services without platform/cli source changes or compile-time dependency on the command owner."

tags_introduced: [] # `cli.command` already exists; this epic implements owner-side catalog and dispatch

config_roots_introduced: []  # `cli` root already exists
artifacts_introduced: []     # CLI owns no artifact schema

adr: "docs/adr/ADR-XXXX-cli-tag-first-command-catalog.md"

ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/modes.md"
- "docs/ssot/observability.md"
- "docs/ssot/sensitive-data-redaction.md"
- "docs/ssot/cache-verify.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/artifacts-and-fingerprint.md"
- "docs/ssot/artifact-generations.md"
- "docs/ssot/compiled-container.md"
- "docs/ssot/runtime-container-definitions.md"
- "docs/ssot/context-keys.md"
- "docs/ssot/context-store.md"
---

### Existing package replacement boundary (MUST)

The existing Phase-0 implementation is not a compatibility baseline.

This epic replaces:

- config-based FQCN command registration;
- direct PHP config loading in the CLI application;
- zero-argument command construction;
- any package-local redaction implementation, sensitive-key classifier, sensitive-value classifier, policy registry, hasher, or pattern registry;
- direct output writing from the command-facing output implementation;
- the combined parser/input implementation;
- the local error-code registry;
- the Phase-0 exception hierarchy.

No compatibility adapters, aliases, deprecated wrappers, dual dispatch paths, or fallback reads from the legacy `cli.commands` list may remain.

Files listed under `Deletes` MUST be absent after implementation.

Files listed as complete rewrites under `Modifies` retain only their paths and public package role, not their current implementation.

### Canonical target and preset ownership (MUST)

Kernel operation commands require:

```text
--target=web|api|console|worker
```

CLI MUST NOT infer the target.

Built-in Kernel operation commands declare only:

```text
--target=<appTarget>
```

They do not declare `--mode` or `--preset`, so descriptor-driven input validation rejects those options for Kernel operations.

Other package commands MAY declare options named `mode` or `preset` when those names have owner-package domain semantics.

Such options MUST NOT affect Kernel Bootstrap preset selection unless the command explicitly invokes a separate Kernel contract that permits it.

The operation request contains only `appTarget`.

Kernel resolves the effective preset through:

```text
presets[appTarget]
→ global preset
→ package default preset
```

CLI renders the effective preset returned by `OpsResult`.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 2.25.0 — Kernel ops façade exists **as a contracts port implementation**:
    - `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` is bound in container to kernel implementation
  - 2.27.0 — Sensitive data redaction boundary exists:
    - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` exists
    - the source-operations host composition that enables `platform.cli` enables one redaction implementation owner
    - exactly one `SensitiveDataRedactorInterface` binding is available before `CliServiceFactory` and `OutputFormatter` are resolved
    - `platform/cli` depends only on the contracts port and does not import or instantiate the concrete redactor

- Required deliverables (exact paths):
  - `docs/ssot/tags.md` contains reserved tag `cli.command` (owner `platform/cli`)
  - `framework/packages/platform/cli/config/cli.php` (subtree file)
  - `framework/packages/platform/cli/config/rules.php`
  - kernel mode defaults/allowed exist under `kernel.modes.*`
  - Foundation declarative definition application and TagRegistry ordering are cemented
  - Kernel source/operations-host boot can run before generated artifacts exist
  - source-host boot reuses the canonical Bootstrap Phase A, config-location, and ConfigKernel Phase B capabilities
  - platform/cli and Kernel Ops MUST NOT introduce a parallel config discovery, loading, merge, or validation pipeline
  - artifact-only application runtime boot remains separate from CLI operations boot

- Required contracts / ports (exact FQCNs):
  - `Coretsia\Contracts\Cli\Input\InputInterface`
  - `Coretsia\Contracts\Cli\Output\OutputInterface`
  - `Coretsia\Contracts\Cli\Command\CommandInterface`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsRequest`
  - `Coretsia\Contracts\Kernel\Ops\OpsResult`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlingContext`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`
  - `Coretsia\Contracts\Context\ContextKeys`
  - `Psr\Container\ContainerInterface`
  - `Psr\Log\LoggerInterface`
- Context boundary:
  - CLI reads canonical context values only through `ContextAccessorInterface`
  - `ContextKeys` belongs to `core/contracts`
  - CLI MUST NOT import or resolve `ContextStore`
  - CLI MUST NOT resolve `CorrelationIdProvider` or create correlation ids
- Allowed Kernel host-bootstrap APIs:
  - `Coretsia\Kernel\Ops\KernelOpsHostInput`
  - `Coretsia\Kernel\Ops\KernelOpsHostBooter`
- Required Foundation and Kernel runtime APIs:
  - `Coretsia\Foundation\Tag\TagRegistry`
  - `Coretsia\Foundation\Tag\ReservedTags`
  - `Coretsia\Foundation\Time\Stopwatch`
  - `Coretsia\Kernel\Runtime\UnitOfWorkType`

- `ResetInterface` is not a baseline CLI dependency because no shared mutable CLI service is introduced.
- A future shared mutable service MUST add `ResetInterface` in the epic that introduces that service.

- External package command contract:
  - any enabled package MAY contribute command services through `cli.command`
  - the command class MUST implement `Coretsia\Contracts\Cli\Command\CommandInterface`
  - the owner package contributes the service and tag through its own provider
  - the owner package owns command arguments, options, domain validation, dependencies, and execution semantics
  - external commands depend only on contracts-level CLI input/output APIs, not platform/cli concrete classes
  - platform/cli MUST NOT import or compile-time depend on a command-owner package solely for discovery or dispatch
  - Worker commands are one compatibility fixture, not a special discovery path
  - migration, database, queue, storage, integration, and application commands MUST use the same mechanism
  - `CommandInterface::run(InputInterface $input, OutputInterface $output): int` returns the owner-selected process exit code
  - portable command exit codes MUST be integers from `0` through `255`

> Built-in Kernel operation commands consume Kernel operations only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`.
>
> `CliHostBootstrap` is the only platform/cli production class allowed to import:
>
> - `Coretsia\Kernel\Ops\KernelOpsHostInput`
> - `Coretsia\Kernel\Ops\KernelOpsHostBooter`
>
> All commands, providers, catalogs, validators, runners, formatters, and renderers MUST remain independent of concrete Kernel Ops classes.

### Cross-package modification boundary (MUST)

The only files outside `framework/packages/platform/cli/` that this epic may create or modify are:

- `framework/packages/core/contracts/src/Cli/Command/CommandInterface.php`
- `framework/packages/core/contracts/src/Cli/Input/InputInterface.php`
- `framework/packages/platform/worker/src/Console/WorkerStartCommand.php`
- `framework/packages/platform/worker/src/Console/WorkerStopCommand.php`
- `framework/packages/platform/worker/src/Console/WorkerStatusCommand.php`
- `framework/packages/platform/worker/src/Provider/WorkerServiceProvider.php`
- `framework/packages/platform/worker/tests/Contract/WorkerCommandMetadataConstantsTest.php`
- `framework/packages/platform/worker/tests/Contract/WorkerServiceProviderCliCommandTaggingTest.php`
- `framework/packages/platform/worker/tests/Integration/WorkerProviderSourceDefinitionsParityTest.php`
- `docs/adr/ADR-XXXX-cli-tag-first-command-catalog.md`
- `docs/ssot/tags.md`
- `docs/ssot/observability.md`
- `docs/adr/INDEX.md`
- `coretsia`
- `framework/bin/coretsia`

No other command-owner package may be modified solely to implement the CLI host.

### Kernel operations consumption boundary (MUST)

`platform/cli` is a transport, command-routing, and presentation layer.

Generic command flow:

```text
parsed command input
→ separate CLI-global format|color
→ CommandCatalog
→ CommandDescriptor
→ CommandInputValidator
→ Kernel UnitOfWork
→ lazy command service resolution inside the UoW callback
→ Coretsia\Contracts\Cli\Command\CommandInterface
→ owner-package services
→ CommandOutputBuffer
→ deterministic formatter
→ console writer
```

Built-in Kernel operation command flow:

```text
Kernel operation command
→ KernelOpsRequestResolver
→ KernelOpsRequest(appTarget)
→ Coretsia\Contracts\Kernel\Ops\KernelOpsInterface
→ safe OpsResult
→ CommandOutputBuffer
```

`KernelOpsRequestResolver` and `KernelOpsInterface` apply only to the built-in Kernel operation commands.

External package commands MUST NOT be routed through `KernelOpsInterface` unless they are explicitly invoking a Kernel-owned operation.

CLI commands, providers, catalog services, runners, formatters, and renderers MUST NOT orchestrate or resolve Kernel compile-time internals.

Module resolution, provider planning, config compilation, runtime graph compilation, generation publication, fingerprint calculation, current-generation location, and cache verification are Kernel-owned operations.

`OpsResult` is already safe by construction. CLI redaction is defense in depth for CLI-owned output and diagnostics; it MUST NOT be used to sanitize raw Kernel config, env, Composer metadata, or artifact payloads.

### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- command-owner packages in platform/cli production source, including:
  - `platform/worker`
  - migration or database packages
  - queue or scheduler packages
  - `integrations/*`
- external console frameworks / parsers
- filesystem scanning for command discovery

- direct use from `platform/cli` production source of:
  - `Coretsia\Kernel\Module\ModulePlanResolver`
  - `Coretsia\Kernel\Module\ModuleResolution`
  - `Coretsia\Kernel\Container\Provider\ContainerProviderPlan`
  - `Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver`
  - `Coretsia\Contracts\Module\ManifestReaderInterface`
  - `Coretsia\Kernel\Module\ComposerManifestReader`
  - `Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler`
  - `Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator`
  - `Coretsia\Kernel\Artifacts\Verifier\CacheVerifier`
  - `Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator`
  - `Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPublisher`
  - `Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator`
  - `Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver`
  - `Coretsia\Kernel\Artifacts\Php\PhpArtifactReader`
  - `Coretsia\Kernel\Boot\ArtifactRuntimeBooter`
  - `Coretsia\Kernel\Boot\ArtifactRuntimeInput`

`platform/cli` infrastructure and built-in Kernel operation commands MUST NOT:

- read Kernel `current`;
- parse Kernel generation manifests;
- resolve Kernel generation directories;
- include Kernel PHP artifact files;
- construct Kernel artifact paths;
- boot the compiled application runtime;
- inspect Kernel `config.php` or `container.php` directly.

All Kernel generation access initiated by built-in Kernel commands occurs behind `KernelOpsInterface`.

Commands contributed by other packages MAY access owner-package files or artifacts through owner-owned services and contracts. They MUST NOT access Kernel artifact internals except through an explicit Kernel public port.

These symbols belong to Kernel-side operation orchestration. Their presence in CLI commands, providers, runners, catalog services, formatters, or renderers is an architecture violation.

- Integration tests MAY enable `platform.worker` only through a composed test fixture/app.
- `platform/cli` production source MUST NOT import external command-owner namespaces solely for command discovery or dispatch.
- owner-package classes MAY appear only in composed integration tests.

### Entry points / integration points (MUST)

- CLI entrypoint (packaged):
  - `framework/packages/platform/cli/bin/coretsia` (declared in composer.json `"bin"`)
- Monorepo canonical wrappers (Prelude compliance):
  - repo-root `coretsia` and `framework/bin/coretsia` MUST remain the canonical entrypoints
  - wrappers MAY delegate to `vendor/bin/coretsia` but MUST be CWD-independent

- CLI has two explicit boot paths.

#### Ultra-early doctor path

```text
bin/coretsia
→ minimal syntax pre-parse
→ exact command token is doctor
→ UltraEarlyDoctorRunner
→ the same dependency-free DoctorCommand used by the normal catalog
→ no KernelOpsHostBooter
→ no command catalog
→ no module discovery
→ no generated artifacts
```

`doctor` is the only command allowed to bypass the normal tag-backed catalog dispatch path.

`DoctorCommand` MUST also be registered and tagged in the source operations host so that `list` and `help` describe the same command metadata used by the ultra-early path.

#### Normal CLI path

```text
bin/coretsia
→ CliHostBootstrap
→ KernelOpsHostInput
→ KernelOpsHostBooter
→ source operations container for AppTarget::Console
→ CommandCatalog
→ descriptor selection
→ structural input validation
→ Kernel UnitOfWork
→ lazy command resolution inside the UoW callback
→ command execution
```

The normal CLI path:

- MUST work without an artifact root or `current`;
- MUST NOT call `ArtifactRuntimeBooter`;
- MUST NOT boot from `container.php`;
- MUST NOT use generated config as source-host configuration;
- MUST compose enabled external package providers through Kernel-owned module/provider planning;
- MUST NOT read Composer metadata from platform/cli;
- MUST NOT become a fallback for HTTP or Worker production runtime.

- Commands:
  - `coretsia doctor`
  - `coretsia list`
  - `coretsia help [<command>]`
  - `coretsia debug:modules --target=<target>`
  - `coretsia config:validate --target=<target>`
  - `coretsia config:debug --target=<target>`
  - `coretsia config:compile --target=<target>`
  - `coretsia config:hash --target=<target>`
  - `coretsia cache:verify --target=<target>`

### Command discovery (tag-first, deterministic) (MUST)

- [ ] the only container-backed discovery mechanism for normal commands is DI tag `cli.command`
- [ ] the exact ultra-early `doctor` invocation is an entrypoint-owned exception and does not perform discovery
- [ ] once the normal operations host exists, `doctor` MUST appear as a tagged descriptor for `list` and `help`
- [ ] CLI MUST NOT read any `cli.commands` registry list.
- [ ] Consumer MUST NOT re-sort or re-dedupe TagRegistry output.

- [ ] External package commands:
  - [ ] CLI catalog MUST discover commands contributed by other enabled packages through `cli.command`.
  - [ ] CLI MUST NOT special-case `platform/worker`.
  - [ ] CLI MUST NOT depend on `platform/worker` at compile time.
  - [ ] Worker command discovery, when tested, MUST happen through the same generic `cli.command` mechanism as all other package commands.

### Command identity ownership (MUST)

- [ ] command name MUST be declared by the command class
- [ ] every tagged command class MUST expose:
  - [ ] `public const string NAME`
  - [ ] `public const string SUMMARY`
  - [ ] `public const string GROUP`
  - [ ] `public const bool HIDDEN`
  - [ ] `public const array ARGUMENTS`
  - [ ] `public const array OPTIONS`
- [ ] `CommandInterface::name()` MUST return the same value as tag metadata `name`
- [ ] command provider MUST reference command class constants when tagging commands
- [ ] command provider MUST NOT invent command names as unrelated string literals
- [ ] command name MUST match:
  - [ ] `\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z`
- [ ] every `cli.command` service id MUST be the exact command class FQCN
- [ ] the service-id class MUST exist and implement `Coretsia\Contracts\Cli\Command\CommandInterface`
- [ ] `CommandCatalog` MUST read command constants through the service-id class without resolving or instantiating the service

### `cli.command` tag metadata schema (MUST)

- [ ] exact required keys:
  - [ ] `name`
  - [ ] `summary`
  - [ ] `group`
  - [ ] `hidden`
  - [ ] `arguments`
  - [ ] `options`
- [ ] unknown metadata keys MUST hard-fail deterministically
- [ ] metadata key `priority` MUST be forbidden
- [ ] canonicalized base tag metadata MUST equal the command class constants before `CommandOverrides` is applied
- [ ] `CommandOverrides` MAY modify only the catalog-view fields `summary|hidden|group`; it MUST NOT mutate command identity, arguments, options, service id, or original tag metadata
- [ ] metadata MUST NOT contain closures, objects, resources, floats, raw config values, runtime filesystem path values, runtime endpoints, env values, secrets, tokens, or runtime payloads
- [ ] argument and option names or summaries MAY describe owner-domain path inputs; runtime path values and path defaults MUST NOT be stored in tag metadata

### Command priority policy (MUST)

- [ ] command tags MUST NOT define `priority`
- [ ] command routing MUST NOT use priority
- [ ] duplicate command names MUST hard-fail deterministically
- [ ] reserved command names MUST hard-fail for external commands:
  - [ ] `help`
  - [ ] `list`
- [ ] reserved names are allowed only for the built-in command service ids registered by `platform/cli`
- [ ] CLI consumer MUST preserve TagRegistry order
- [ ] CLI consumer MUST NOT re-sort TagRegistry output
- [ ] CLI consumer MUST NOT silently de-dupe TagRegistry output
- [ ] every `cli.command` tagged service MUST have actual tag priority `0`
- [ ] a non-zero `TaggedService::priority()` hard-fails before descriptor construction
- [ ] CLI MUST NOT normalize, ignore, or overwrite a non-zero priority

### Lazy command discovery (MUST)

- [ ] `CommandCatalog` MUST build descriptors from `cli.command` tag metadata
- [ ] `CommandCatalog` MUST NOT instantiate command services while building the catalog
- [ ] `list` MUST be renderable from descriptors without instantiating all command services
- [ ] `help` MUST be renderable from descriptors without instantiating all command services
- [ ] command service MUST be resolved only when the selected command is dispatched
- [ ] on dispatch, resolved service MUST implement `Coretsia\Contracts\Cli\Command\CommandInterface`
- [ ] on dispatch, resolved command `name()` MUST match descriptor/tag metadata `name`
- [ ] mismatch MUST hard-fail with `InvalidCommandTagMetaException`

### Kernel operation command exit codes (MUST)

These mappings apply only to built-in Kernel operation commands.

Commands contributed by other packages own their domain result-to-exit-code mapping through the shared command contract. `platform/cli` MUST NOT reinterpret owner-package outcomes except for global parsing, bootstrap, and uncaught execution failures.

```text
0
operation completed with a positive result

1
invalid command input, invalid config, handled Kernel operation failure,
or `KernelOpsFailedException`

2
cache verification completed with state `dirty`

3
cache verification completed with state `invalid`
```

Specific mapping:

```text
config:validate valid=true  -> 0
config:validate valid=false -> 1

config:compile success -> 0
config:hash success    -> 0

cache:verify clean   -> 0
cache:verify dirty   -> 2
cache:verify invalid -> 3
```

CLI MUST NOT derive these states by inspecting artifact files.

It maps only the safe `OpsResult` returned by Kernel.

### Deliverables (exact paths only) (MUST)

#### Creates

- [ ] `framework/packages/platform/cli/src/Output/CliOutputPolicy.php`
  - [ ] immutable readonly value object
  - [ ] created only from validated config
  - [ ] contains:
    - [ ] `formatDefault: string`
    - [ ] `interactiveFormat: string`
    - [ ] `nonInteractiveFormat: string`
    - [ ] `colorDefault: string`
    - [ ] `tableMaxWidth: int`
  - [ ] validates canonical tokens defensively
  - [ ] contains no config repository
  - [ ] contains no streams
  - [ ] contains no terminal detection
  - [ ] contains no runtime global-option overrides
  - [ ] contains no redaction toggle
  - [ ] contains no mutable state

- [ ] `framework/packages/platform/cli/src/Output/TerminalCapabilities.php`
  - [ ] immutable readonly runtime-input value
  - [ ] contains:
    - [ ] `interactive: bool`
    - [ ] `ansiSupported: bool`
  - [ ] created once for one CLI invocation
  - [ ] terminal probing occurs only at the packaged entrypoint/bootstrap boundary
  - [ ] formatters and commands MUST NOT call `stream_isatty()` directly
  - [ ] formatters and commands MUST NOT perform Windows terminal probing directly
  - [ ] tests can inject deterministic capabilities
  - [ ] contains no config and no output policy
  - [ ] `interactive` means stdout is interactive
  - [ ] `ansiSupported` is true only when ANSI is safe for both stdout and stderr

- [ ] `framework/packages/platform/cli/src/Output/TerminalCapabilitiesDetector.php`
  - [ ] is the only platform/cli service allowed to probe terminal capabilities
  - [ ] receives explicit stdout and stderr stream resources from the entrypoint
  - [ ] stdout determines adaptive-format interactivity
  - [ ] ANSI support requires both output streams to accept ANSI safely
  - [ ] detects:
    - [ ] interactive output
    - [ ] ANSI support
  - [ ] returns immutable `TerminalCapabilities`
  - [ ] MUST NOT read CLI config
  - [ ] MUST NOT read command options
  - [ ] MUST NOT read CI environment variables
  - [ ] MUST NOT retain streams
  - [ ] tests can replace it with deterministic capabilities

- [ ] `framework/packages/platform/cli/src/Bootstrap/CliEntrypointPaths.php`
  - [ ] immutable readonly value object
  - [ ] contains:
    - [ ] normalized Composer autoload path
    - [ ] normalized application skeleton root
  - [ ] performs no filesystem traversal or resolution
  - [ ] exposes no public diagnostic rendering

- [ ] `framework/packages/platform/cli/src/Bootstrap/CliEntrypointPathsResolver.php`
  - [ ] pure entrypoint-layout resolver
  - [ ] unresolved or conflicting post-autoload layout throws `CliBootstrapException`
  - [ ] canonical API:
    - [ ] `public function resolve(string $launcherFile, string $autoloadPath, ?string $composerBinDir, ?string $explicitSkeletonRoot): CliEntrypointPaths`
  - [ ] receives an already-selected readable autoload path
  - [ ] validates and normalizes that path
  - [ ] explicit skeleton root is used by canonical monorepo wrappers
  - [ ] otherwise the skeleton root is derived only from the canonical Composer binary layout
  - [ ] exactly one skeleton-root source may be active
  - [ ] supports:
    - [ ] Composer binary proxy variables
    - [ ] monorepo canonical wrapper layout
  - [ ] independent of current working directory
  - [ ] MUST NOT recursively scan arbitrary parent directories
  - [ ] MUST NOT read application config
  - [ ] unresolved layout throws a deterministic path-safe CLI bootstrap exception

- [ ] `framework/packages/platform/cli/src/Bootstrap/UltraEarlyDoctorRunner.php`
  - [ ] used only when the exact command token is `doctor`
  - [ ] creates invocation-local `ArgvInput`
  - [ ] creates invocation-local `CommandOutputBuffer`
  - [ ] invokes the same `DoctorCommand` class registered in the normal catalog
  - [ ] uses fixed `plain` output
  - [ ] forces color disabled
  - [ ] renders only allowlisted doctor record types and scalar values
  - [ ] writes through `ConsoleOutputWriter`
  - [ ] requires no Kernel container, configuration, UoW, tracer, meter, logger, context, or redaction service
  - [ ] MUST NOT expose:
    - [ ] raw environment values
    - [ ] absolute paths
    - [ ] complete loaded-extension lists
    - [ ] phpinfo output
    - [ ] command lines
    - [ ] stack traces
  - [ ] returns a deterministic exit code
  - [ ] canonical API:
    - [ ] `public function run(array $argv, ConsoleOutputWriter $writer): int`
  - [ ] accepts only the exact invocation:
    - [ ] `coretsia doctor`
  - [ ] additional arguments or options fail deterministically through the fixed doctor output pipeline

- [ ] `framework/packages/platform/cli/src/Bootstrap/CliHostBootstrap.php`
  - [ ] is the only platform/cli class allowed to import `KernelOpsHostInput` and `KernelOpsHostBooter`
  - [ ] boots the source operations container
  - [ ] resolves `CliApplication` from that container
  - [ ] contains no command discovery, parsing, formatting, or Kernel operation logic
  - [ ] receives the one invocation-local `TerminalCapabilities` instance created at the entrypoint boundary
  - [ ] terminal capability detection is separate from command parsing and domain logic
  - [ ] passes capabilities into the resolved `CliApplication`
  - [ ] canonical entrypoint API:
    - [ ] `public function run(string $skeletonRoot, array $argv, TerminalCapabilities $capabilities, ConsoleOutputWriter $writer): int`
  - [ ] constructs `KernelOpsHostInput` internally from explicit `skeletonRoot`
  - [ ] boots one source operations container
  - [ ] resolves `CliApplication`
  - [ ] passes `argv|capabilities|writer` as invocation-local values
  - [ ] directly constructs the zero-constructor `KernelOpsHostBooter`
  - [ ] calls exactly one `KernelOpsHostBooter::boot()` invocation
  - [ ] does not expect `KernelOpsHostBooter` to be resolved from the container it creates
  - [ ] MUST NOT register raw argv, streams, writer, capabilities, or output buffer as shared container services
  - [ ] catches failures from:
    - [ ] `KernelOpsHostBooter::boot()`
    - [ ] source-container construction
    - [ ] `CliApplication` resolution
  - [ ] writes one fixed safe diagnostic through `ConsoleOutputWriter`:
    - [ ] code `CORETSIA_CLI_HOST_BOOT_FAILED`
    - [ ] reason `host-boot-failed`
  - [ ] returns exit code `1`
  - [ ] MUST NOT invoke `ErrorHandlerInterface` because the CLI application container may not exist
  - [ ] MUST NOT expose Throwable message, class, trace, provider id, config value, or path

Entrypoint + application:
- [ ] `framework/packages/platform/cli/bin/coretsia`
  - [ ] before using any package class:
    - [ ] select autoload only from Composer binary-proxy input or canonical Coretsia wrapper input
    - [ ] reject conflicting sources
    - [ ] require the selected autoload file exactly once
  - [ ] missing, conflicting, or unreadable autoload uses the sole pre-autoload fallback:
    - [ ] writes `CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable` directly to stderr
    - [ ] exits with code `1`
    - [ ] emits no path, Throwable message, class, or trace
  - [ ] direct stderr use is forbidden after Composer autoload succeeds
  - [ ] PHP executable (`#!/usr/bin/env php`)
  - [ ] reads raw `argv`
  - [ ] performs only the exact ultra-early `doctor` command-token check
  - [ ] for exact `doctor`, delegates to `UltraEarlyDoctorRunner`
  - [ ] for every other command, delegates to `CliHostBootstrap`, then runs the resolved `CliApplication`
  - [ ] contains no catalog, formatting, command-domain, or Kernel operation logic
  - [ ] performs only the minimal pre-autoload selection of one allowed autoload candidate
  - [ ] after Composer autoload succeeds, validates and normalizes the selected autoload path and resolves the skeleton root only through `CliEntrypointPathsResolver`
  - [ ] MUST NOT reproduce skeleton-root or Composer-layout algorithms inline
  - [ ] catches `CliBootstrapException` before the generic uncaught `Throwable` boundary
  - [ ] renders only its stable code and reason through `ConsoleOutputWriter`
  - [ ] owns the final uncaught Throwable boundary
  - [ ] uncaught failure writes exactly a safe fixed diagnostic:
    - [ ] code `CORETSIA_CLI_UNCAUGHT_EXCEPTION`
    - [ ] reason `uncaught-exception`
  - [ ] writes the fixed uncaught diagnostic only through `ConsoleOutputWriter`
  - [ ] binary itself MUST NOT call `fwrite(STDOUT|STDERR)` for rendering
  - [ ] exits with code `1`
  - [ ] MUST NOT render Throwable message, class, trace, previous throwable, or path
  - [ ] MUST NOT depend on the deleted `ErrorCodes` registry

- [ ] `framework/packages/platform/cli/src/Application/CliApplication.php`
  - [ ] owns the normal post-bootstrap command flow:
    - [ ] create one invocation-local `CommandOutputBuffer`
    - [ ] parse into `ParsedCliInvocation`
    - [ ] resolve effective format
    - [ ] resolve effective color
    - [ ] select `CommandDescriptor`
    - [ ] perform descriptor-driven structural validation
    - [ ] call `CommandRunner`
    - [ ] `CommandRunner` lazily resolves and executes the selected command
    - [ ] finalize one `CommandOutputBatch`
    - [ ] format and redact once through `OutputFormatter`
    - [ ] write one `FormattedOutput`
    - [ ] return the command exit code
  - [ ] MUST NOT read `ConfigRepositoryInterface` directly
  - [ ] MUST NOT write ContextStore directly
  - [ ] MUST NOT create correlation or UoW ids
  - [ ] MUST NOT perform terminal capability probing
  - [ ] MUST NOT retain previous command, output batch, format, color, or exit code
  - [ ] both `RedactionViolationException` and `CliOutputFormatException` enter the fixed non-recursive render fallback
  - [ ] canonical API:
    - [ ] `public function run(array $argv, TerminalCapabilities $capabilities, ConsoleOutputWriter $writer): int`
  - [ ] constructor receives:
    - [ ] `ArgvInputParser`
    - [ ] `CommandCatalog`
    - [ ] `CommandInputValidator`
    - [ ] `FormatResolver`
    - [ ] `ColorResolver`
    - [ ] `CommandRunner`
    - [ ] `CliErrorHandler`
    - [ ] `ExceptionRenderer`
    - [ ] `OutputFormatter`
  - [ ] owns the complete normal-path error boundary:
    - [ ] creates an invocation-local `CommandOutputBuffer` before normal parsing begins
    - [ ] catches failures from:
      - [ ] argv parsing
      - [ ] global-option resolution
      - [ ] catalog selection
      - [ ] descriptor validation
      - [ ] lazy service resolution
      - [ ] command execution
    - [ ] passes the Throwable to `CliErrorHandler`
    - [ ] calls `CommandOutputBuffer::discard()` after a thrown failure
    - [ ] passes the resulting `ErrorDescriptor` and the same cleared buffer to `ExceptionRenderer`
    - [ ] finalizes exactly one normalized error record
    - [ ] returns exit code `1`
  - [ ] error rendering format:
    - [ ] uses the resolved output format when resolution completed successfully
    - [ ] otherwise uses fixed `plain` format with color disabled
    - [ ] malformed `--format` or `--color` MUST NOT be reused during error rendering
  - [ ] if `CliErrorHandler`, `ExceptionRenderer`, formatter, or redactor fails:
    - [ ] write one fixed safe plain diagnostic through `ConsoleOutputWriter`
    - [ ] code: `CORETSIA_CLI_RENDER_FAILURE`
    - [ ] reason: `render-failure`
    - [ ] return exit code `1`
    - [ ] do not retry formatting or redaction

Input:
- [ ] `framework/packages/platform/cli/src/Input/ParsedCliInvocation.php`
  - [ ] immutable readonly value
  - [ ] contains:
    - [ ] `ArgvInput commandInput`
    - [ ] nullable `formatOverride: string`
    - [ ] nullable `colorOverride: string`
  - [ ] contains no streams, config repository, catalog, descriptor, or command service

- [ ] `framework/packages/platform/cli/src/Input/ArgvInput.php`
  - [ ] Concrete `InputInterface` implementation; deterministic parse rules (no locale-dependent behavior).
  - [ ] MUST implement expanded `InputInterface`
  - [ ] MUST expose:
    - [ ] raw tokens
    - [ ] command name
    - [ ] positional arguments
    - [ ] normalized options
    - [ ] option lookup by name
    - [ ] boolean flags
  - [ ] `tokens()` contains only command-facing tokens
  - [ ] launcher token and CLI-global options are absent
  - [ ] MUST NOT expose parser internals
  - [ ] MUST NOT require commands to depend on `platform/cli` concrete classes

- [ ] `framework/packages/platform/cli/src/Input/ArgvInputParser.php`
  - [ ] canonical API:
    - [ ] `public function parse(array $argv): ParsedCliInvocation`
  - [ ] structural parsing failures throw `CliInputInvalidException`
  - [ ] removes launcher token before command parsing
  - [ ] global options are recognized only before the `--` end-of-options marker
  - [ ] removes `format|color` from command-facing tokens and options
  - [ ] preserves their values only in `ParsedCliInvocation`
  - [ ] Minimal parser (no external libs): `<command> [--key=val] [--flag] [args...]`; stable precedence rules.
  - [ ] repeated bare flags hard-fail
  - [ ] repeated value options preserve `list<string>` order
  - [ ] MUST parse deterministic syntax:
    - [ ] `<command>`
    - [ ] positional arguments
    - [ ] `--key=value`
    - [ ] `--flag`
    - [ ] repeated `--key=value` into `list<string>`
  - [ ] MUST reject malformed option names deterministically
  - [ ] MUST normalize option names deterministically
  - [ ] MUST NOT use locale-dependent parsing
  - [ ] MUST NOT read environment variables
  - [ ] MUST NOT perform filesystem scanning
  - [ ] preserve repeated options as ordered lists; never silently apply last-value-wins
  - [ ] support `--` as the deterministic end-of-options marker
  - [ ] parse syntax only; descriptor-specific validation belongs to `CommandInputValidator`
  - [ ] separates CLI-global options from command-facing options:
    - [ ] `--format=<adaptive|json|table|plain>`
    - [ ] `--color=<auto|always|never>`
  - [ ] preserves both global tokens for their resolvers
  - [ ] command-facing `InputInterface` MUST NOT expose `format` or `color`
  - [ ] repeated global options hard-fail
  - [ ] empty global option values hard-fail

- [ ] `framework/packages/platform/cli/src/Input/CommandInputValidator.php`
  - [ ] `public function validate(CommandDescriptor $descriptor, ArgvInput $input): void`
  - [ ] receives selected `CommandDescriptor` and parsed `ArgvInput`
  - [ ] validates arguments and options before resolving the command service
  - [ ] structural descriptor/input mismatches throw `CliInputInvalidException`
  - [ ] rejects unknown options
  - [ ] rejects missing required option values
  - [ ] rejects repeated non-repeatable options
  - [ ] rejects unsupported positional arguments
  - [ ] validates required/optional/variadic argument counts
  - [ ] performs structural validation only
  - [ ] validates only structure declared by the selected command descriptor
  - [ ] MUST NOT validate Kernel or owner-package domain semantics
  - [ ] MUST NOT instantiate the command service
  - [ ] `value = none` accepts only a bare boolean flag
  - [ ] `value = required` accepts only `--name=value`
  - [ ] `value = optional` accepts a bare flag or one scalar value
  - [ ] repeatable required-value options must arrive as `list<string>`

Output:
- [ ] `framework/packages/platform/cli/src/Output/CommandOutputBuffer.php`
  - [ ] implements `OutputInterface`
  - [ ] created as a new local instance for one `CliApplication::run()` invocation
  - [ ] preserves `text()`, `json()`, and `error()` records in call order
  - [ ] MUST NOT write stdout/stderr
  - [ ] MUST NOT be a shared container service
  - [ ] local per-invocation state does not require `kernel.stateful`
  - [ ] `finalize(): CommandOutputBatch`
  - [ ] `discard(): void`
  - [ ] `discard()` clears every buffered record and leaves the buffer writable
  - [ ] `discard()` MUST NOT be called after finalization
  - [ ] `discard()` is used only by the `CliApplication` error boundary
  - [ ] buffer cannot be written after finalization
  - [ ] every `text|json|error` call normalizes immediately
  - [ ] normalizes `CRLF|CR` to `LF`
  - [ ] rejects NUL and ESC bytes
  - [ ] rejects C0/C1 control bytes except normalized LF and TAB
  - [ ] applies the same string policy recursively to JSON-like payload keys and values
  - [ ] owner-package output cannot inject raw ANSI sequences

- [ ] `framework/packages/platform/cli/src/Output/CommandOutputBatch.php`
  - [ ] immutable finalized ordered record list
  - [ ] exact record shapes:
    - [ ] text:
      - [ ] `type = text`
      - [ ] `text: string`
    - [ ] json:
      - [ ] `type = json`
      - [ ] `payload: json-like array`
    - [ ] error:
      - [ ] `type = error`
      - [ ] `code: non-empty safe token`
      - [ ] `message: non-empty safe single-line string`
  - [ ] recursively rejects floats, objects, resources, closures, and Throwables
  - [ ] recursively `strcmp`-sorts map keys
  - [ ] preserves list and record order
  - [ ] contains no streams or formatter state

- [ ] `framework/packages/platform/cli/src/Output/FormattedOutput.php`
  - [ ] stdout and stderr payloads MUST NOT end with CR or LF
  - [ ] final-newline ownership belongs exclusively to `ConsoleOutputWriter`
  - [ ] immutable readonly value
  - [ ] contains:
    - [ ] `stdoutBytes: string`
    - [ ] `stderrBytes: string`
  - [ ] contains no streams
  - [ ] contains no ANSI when effective color is disabled
  - [ ] stores stdout and stderr bytes without trailing CR or LF

- [ ] `framework/packages/platform/cli/src/Output/ConsoleOutputWriter.php`
  - [ ] invocation-local final output sink
  - [ ] constructor receives explicit stdout and stderr stream resources
  - [ ] canonical API:
    - [ ] `public function write(FormattedOutput $output): void`
  - [ ] writes only already-formatted bytes
  - [ ] owns deterministic final-newline policy
  - [ ] writes exactly one final newline for each non-empty stream payload
  - [ ] retains no previous output
  - [ ] commands MUST NOT receive this service

- [ ] `framework/packages/platform/cli/src/Output/FormatResolver.php`
  - [ ] `public function resolve(?string $explicitFormat, TerminalCapabilities $capabilities): string`
  - [ ] consumes:
    - [ ] nullable explicit global format token
    - [ ] `CliOutputPolicy`
    - [ ] `TerminalCapabilities`
  - [ ] precedence:
    - [ ] explicit `--format`
    - [ ] `cli.output.format_default`
  - [ ] when effective token is not `adaptive`, returns it unchanged
  - [ ] when effective token is `adaptive`:
    - [ ] interactive terminal → configured interactive format
    - [ ] non-interactive terminal → configured non-interactive format
  - [ ] allowed effective values: `json|table|plain`
  - [ ] returns no `adaptive` token after resolution
  - [ ] MUST NOT inspect CI environment variables
  - [ ] MUST NOT inspect command-owner options
  - [ ] MUST NOT read config directly
  - [ ] deterministic for the same policy, global token, and terminal capabilities

- [ ] `framework/packages/platform/cli/src/Output/ColorResolver.php`
  - [ ] `public function resolve(?string $explicitColor, TerminalCapabilities $capabilities, string $effectiveFormat): bool`
  - [ ] consumes:
    - [ ] nullable explicit global color token
    - [ ] `CliOutputPolicy`
    - [ ] `TerminalCapabilities`
    - [ ] effective output format
  - [ ] precedence:
    - [ ] explicit `--color`
    - [ ] `cli.output.color_default`
  - [ ] resolves:
    - [ ] `always` → enabled
    - [ ] `never` → disabled
    - [ ] `auto` → enabled only when output is interactive and ANSI is supported
  - [ ] JSON format always forces color disabled
  - [ ] MUST NOT read config directly
  - [ ] MUST NOT inspect command-owner options
  - [ ] MUST NOT expose terminal details to commands
  - [ ] returns one boolean effective color decision

- [ ] `framework/packages/platform/cli/src/Output/Ansi/AnsiDecorator.php`
  - [ ] owns the fixed semantic ANSI mapping
  - [ ] semantic roles:
    - [ ] heading
    - [ ] success
    - [ ] warning
    - [ ] error
    - [ ] muted
  - [ ] applies ANSI only when effective color is enabled
  - [ ] MUST NOT decorate JSON
  - [ ] MUST NOT inspect config
  - [ ] MUST NOT expose raw ANSI codes to commands
  - [ ] MUST NOT allow user-defined escape sequences
  - [ ] MUST NOT add ANSI to redirected output in `auto` mode
  - [ ] output with color disabled is byte-stable and escape-free

Output (deterministic + redacted):
- [ ] `framework/packages/platform/cli/src/Output/OutputFormatter.php`
  - [ ] MUST be a stateless one-call transformer
  - [ ] MUST NOT implement `begin|add|flush` accumulation
  - [ ] MUST NOT be tagged `kernel.stateful` or `kernel.reset`
  - [ ] MUST consume `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` for sensitive output summaries.
  - [ ] `framework/packages/platform/cli/src/Output/Redaction/*` MUST NOT exist in this package.
  - [ ] `framework/packages/platform/cli/src/Redaction/*` MUST NOT exist in this package.
  - [ ] receives effective format and effective color as explicit invocation inputs
  - [ ] MUST NOT read CLI config
  - [ ] MUST NOT read ContextStore
  - [ ] redaction is always applied as defense in depth
  - [ ] redacts normalized output records before concrete formatting
  - [ ] MUST NOT redact an already-encoded JSON document
  - [ ] revalidates redacted records as json-like and control-byte-safe
  - [ ] recursively `strcmp`-sorts every redacted map before concrete formatting
  - [ ] preserves list and record order after redaction
  - [ ] redaction MUST NOT introduce invalid map keys, floats, objects, resources, or control bytes
  - [ ] redaction cannot be disabled by config or command option
  - [ ] formatter/redactor failures map to deterministic safe CLI failure
  - [ ] redactor failure is wrapped as `RedactionViolationException`
  - [ ] concrete formatter failure is wrapped as `CliOutputFormatException`
  - [ ] neither wrapper copies the previous Throwable message
  - [ ] `RedactionViolationException` contains only:
    - [ ] code `CORETSIA_CLI_REDACTION_VIOLATION`
    - [ ] reason `output-redaction-failed`
  - [ ] `CliOutputFormatException` contains only:
    - [ ] code `CORETSIA_CLI_OUTPUT_FORMAT_FAILED`
    - [ ] reason `output-format-failed`
  - [ ] neither exception copies the previous Throwable message
  - [ ] canonical API:
    - [ ] `public function format(CommandOutputBatch $batch, ?string $commandName, int $exitCode, string $format, bool $color): FormattedOutput`
  - [ ] command name is null for failures that occur before descriptor selection
  - [ ] delegates to exactly one concrete formatter
  - [ ] JSON output:
    - [ ] writes one document to stdout
    - [ ] leaves stderr empty
  - [ ] plain/table output:
    - [ ] text and json-derived records go to stdout
    - [ ] error records go to stderr

- [ ] `framework/packages/platform/cli/src/Output/Formatter/JsonFormatter.php` — stable schema `schema, meta, data`
  - [ ] Stateless formatter: produces deterministic JSON (stable key order, stable schema envelope, no runtime caches).
  - [ ] MUST NOT emit ANSI escape sequences
  - [ ] MUST NOT emit terminal-width-dependent output
  - [ ] MUST ignore effective color even when global color is `always`
  - [ ] `meta.command` is `string|null`
  - [ ] null is used only when descriptor selection did not complete
  - [ ] produces stream payloads without trailing CR or LF

- [ ] `framework/packages/platform/cli/src/Output/Formatter/TableFormatter.php` — safe table output
  - [ ] Stateless table renderer: no cross-run width/column memory; compute from current payload only.
  - [ ] constructor receives configured `maxWidth`
  - [ ] honors `cli.output.table.max_width`
  - [ ] width handling is deterministic and does not query terminal width
  - [ ] MAY use `AnsiDecorator` only when color is enabled
  - [ ] visible-width calculation MUST ignore ANSI escape bytes
  - [ ] produces stream payloads without trailing CR or LF

- [ ] `framework/packages/platform/cli/src/Output/Formatter/PlainFormatter.php` — safe plain output
  - [ ] Stateless plain renderer; no hidden global formatting state.
  - [ ] MAY use `AnsiDecorator` only when color is enabled
  - [ ] color-disabled output is byte-stable
  - [ ] produces stream payloads without trailing CR or LF

Output schema:
- [ ] `framework/packages/platform/cli/resources/schema/cli_output@1.json`
  - [ ] JSON Schema for rendered CLI payloads (no floats; no secrets; deterministic shape)
  - [ ] Used by `JsonOutputSchemaContractTest.php` as the single source of truth
  - [ ] exact top-level shape:
    - [ ] `schema = coretsia.cli-output@1`
    - [ ] `meta`
    - [ ] `data`
  - [ ] `meta` exact keys:
    - [ ] `command`
    - [ ] `outcome`
    - [ ] `exit_code`
  - [ ] `outcome` is derived from exit code:
    - [ ] `0` → `success`
    - [ ] non-zero → `failure`
  - [ ] `data.records` contains the normalized ordered output records
  - [ ] unknown keys are rejected
  - [ ] JSON maps use deterministic key order

Catalog:
- [ ] `framework/packages/platform/cli/src/Catalog/CommandCatalog.php` — builds deterministic catalog from `cli.command` tags
  - [ ] is one frozen immutable catalog built from final tag-registry input and validated command overrides
  - [ ] contains no mutable cache and requires no reset
  - [ ] MUST consume only `ReservedTags::CLI_COMMAND`
  - [ ] MUST build descriptors from tag metadata
  - [ ] MUST preserve TagRegistry order
  - [ ] MUST NOT re-sort discovery output
  - [ ] MUST NOT silently de-dupe discovery output
  - [ ] duplicate command names MUST hard-fail with `InvalidCommandTagMetaException`
  - [ ] reserved external command name collisions MUST hard-fail:
    - [ ] `help`
    - [ ] `list`
  - [ ] reserved names MUST be allowed only for built-in service ids registered by `platform/cli`
  - [ ] MUST NOT instantiate command services while building descriptors
  - [ ] MUST NOT depend on `platform/worker`
  - [ ] MUST NOT use filesystem scanning
  - [ ] MUST NOT read `cli.commands` registry list
  - [ ] rejects every tagged command whose actual tag priority is not `0`
  - [ ] constructor receives:
    - [ ] `TagRegistry`
    - [ ] `CommandTagSchema`
    - [ ] `CommandOverrides`
    - [ ] validated `cli.commands.overrides` map
    - [ ] immutable reserved built-in service-id map
  - [ ] consumes exactly `TagRegistry::all(ReservedTags::CLI_COMMAND)`
  - [ ] validates actual priority and metadata for every tagged service
  - [ ] builds the complete base descriptor map
  - [ ] applies `CommandOverrides` exactly once after base validation
  - [ ] canonical API:
    - [ ] `public function descriptors(): array`
    - [ ] `public function require(string $name): CommandDescriptor`
  - [ ] `require()` performs no command service resolution

- [ ] `framework/packages/platform/cli/src/Catalog/CommandTagSchema.php`
  - [ ] validates `TaggedService::id()` as the exact command class FQCN
  - [ ] validates `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS` against the canonicalized tag metadata without resolving the command service
  - [ ] validates exact required metadata keys:
    - [ ] `name`
    - [ ] `summary`
    - [ ] `group`
    - [ ] `hidden`
    - [ ] `arguments`
    - [ ] `options`
  - [ ] validates canonical command-name regex
  - [ ] rejects `priority` and unknown keys
  - [ ] validates ordered argument descriptors:
    - [ ] exact keys `name|summary|required|variadic`
    - [ ] unique names
    - [ ] required before optional
    - [ ] variadic only last
  - [ ] `arguments` and `options` MUST be lists, not maps
  - [ ] argument descriptor:
    - [ ] `name` matches `\A[a-z][a-z0-9-]*\z`
    - [ ] `summary` is non-empty safe text
    - [ ] `required` is bool
    - [ ] `variadic` is bool
  - [ ] option descriptor:
    - [ ] `name` matches `\A[a-z][a-z0-9-]*\z`
    - [ ] `summary` is non-empty safe text
    - [ ] `value` is `none|required|optional`
    - [ ] `repeatable` is bool
  - [ ] `repeatable = true` is allowed only with `value = required`
  - [ ] metadata contains no default runtime values
  - [ ] validates ordered option descriptors:
    - [ ] exact keys `name|summary|value|repeatable`
    - [ ] `value = none|required|optional`
    - [ ] unique names
    - [ ] repeated non-repeatable option is invalid
    - [ ] `format|color` are the baseline reserved global option names
    - [ ] `help`, `mode`, `preset`, and other names remain available to owner packages unless introduced as global options by a separate CLI contract
  - [ ] rejects closures, objects, resources, floats, runtime filesystem path values, runtime endpoints, secrets, and non-json-like metadata
  - [ ] does not reject an argument or option merely because its name or summary describes a path
  - [ ] throws `InvalidCommandTagMetaException`

- [ ] `framework/packages/platform/cli/src/Catalog/CommandOverrides.php`
  - [ ] stateless overlay applier
  - [ ] constructor has no catalog or config state
  - [ ] invalid override structure or value type throws `CliConfigInvalidException`
  - [ ] canonical API:
    - [ ] `public function apply(array $baseDescriptors, array $overrides): array`
  - [ ] returns one immutable overridden descriptor map
  - [ ] validates every dynamic override key against canonical command-name regex
  - [ ] rejects an override for an unknown command
  - [ ] validates each override as a map
  - [ ] allows exactly `summary|hidden|group`
  - [ ] `summary` must be a non-empty safe string
  - [ ] `hidden` must be bool
  - [ ] `group` must be a safe group token
  - [ ] unknown fields hard-fail deterministically
  - [ ] cannot change:
    - [ ] command name
    - [ ] service id
    - [ ] arguments
    - [ ] options
    - [ ] base tag metadata
  - [ ] cannot introduce commands or aliases
  - [ ] produces an immutable catalog view

- [ ] `framework/packages/platform/cli/src/Catalog/CommandDescriptor.php` — canonical immutable command descriptor
  - [ ] Stateless immutable DTO (readonly). No derived caches; safe to share.
  - [ ] MUST be internal CLI catalog DTO built from tag metadata
  - [ ] MUST NOT be imported by external packages
  - [ ] MUST include:
    - [ ] service id
    - [ ] name
    - [ ] summary
    - [ ] group
    - [ ] hidden
    - [ ] arguments
    - [ ] options
  - [ ] MUST be immutable / readonly
  - [ ] MUST NOT contain command object instance
  - [ ] MUST NOT contain closures
  - [ ] MUST NOT contain raw config values, secrets, paths, endpoints, or payloads

Kernel operation request resolver:
- [ ] `framework/packages/platform/cli/src/Kernel/KernelOpsRequestResolver.php`
  - [ ] `public function resolve(InputInterface $input): KernelOpsRequest`
  - [ ] invalid scalar target shape throws `CliInputInvalidException`
  - [ ] is used only by built-in Kernel operation commands
  - [ ] consumes only the already structurally validated `InputInterface`
  - [ ] requires exactly one scalar `target` option
  - [ ] validates only non-empty safe token shape
  - [ ] constructs `KernelOpsRequest(appTarget)`
  - [ ] relies on `CommandInputValidator` and the selected descriptor to reject undeclared, missing-value, or repeated options before command service resolution
  - [ ] does not import or receive `CommandDescriptor`
  - [ ] does not import Kernel `AppTarget`
  - [ ] does not load Bootstrap configuration or mode presets
  - [ ] does not inspect artifacts or `current`
  - [ ] semantic target validation belongs to Kernel Ops

Runner + diagnostics:
- [ ] `framework/packages/platform/cli/src/Runner/CommandRunner.php`
  - [ ] `public function run(CommandDescriptor $descriptor, InputInterface $input, OutputInterface $output, string $outputFormat): int`
  - [ ] service resolution and exit-code validation occur inside the UoW callback
  - [ ] invalid exit code throws before `KernelRuntimeInterface::runUnitOfWork()` returns
  - [ ] resolves the selected command through `ContainerInterface`
  - [ ] executes `CommandInterface::run()` through `KernelRuntimeInterface`
  - [ ] on Throwable:
    - [ ] records only safe failure observability
    - [ ] allows `KernelRuntimeInterface` to complete after/reset lifecycle
    - [ ] rethrows the original Throwable
  - [ ] MUST NOT depend on `ErrorHandlerInterface`
  - [ ] MUST NOT depend on `ExceptionRenderer`
  - [ ] MUST NOT render command failures
  - [ ] MUST resolve command service only after catalog selects a descriptor
  - [ ] resolved service MUST implement `CommandInterface`
  - [ ] resolved command `name()` MUST equal descriptor name
  - [ ] mismatch MUST throw `InvalidCommandTagMetaException`
  - [ ] MUST pass parsed `InputInterface` to command
  - [ ] MUST pass `OutputInterface` to command
  - [ ] MUST NOT let commands parse raw argv through platform-specific classes
  - [ ] MUST execute runtime commands inside kernel UoW wrapper
  - [ ] MUST NOT instantiate all commands for `list` / `help`
  - [ ] preserves the integer exit code returned by an owner-package command
  - [ ] validates that the returned exit code is within `0..255`
  - [ ] invalid return codes fail through `CliCommandFailedException`
  - [ ] MUST NOT remap a valid owner-package exit code
  - [ ] built-in Kernel operation commands perform their `OpsResult` mapping inside their own command implementation
  - [ ] constructor receives:
    - [ ] `KernelRuntimeInterface`
    - [ ] `ContextAccessorInterface`
    - [ ] `TracerPortInterface`
    - [ ] `MeterPortInterface`
    - [ ] `LoggerInterface`
    - [ ] `Psr\Container\ContainerInterface`
    - [ ] Foundation `Stopwatch`
  - [ ] resolves only `CommandDescriptor::serviceId()` through `ContainerInterface`
  - [ ] resolution happens only after:
    - [ ] catalog selection
    - [ ] global-option separation
    - [ ] descriptor-driven input validation
  - [ ] runner resolves only the selected `ListCommand` or `HelpCommand`
  - [ ] those commands render descriptors without resolving any other command service
  - [ ] MUST NOT enumerate container services
  - [ ] MUST NOT resolve all tagged command services eagerly
  - [ ] executes one selected command through one canonical UoW:
    - [ ] UoW type: `cli`
    - [ ] safe operation id: selected descriptor command name
    - [ ] safe UoW attribute: effective output format
  - [ ] MUST NOT:
    - [ ] write ContextStore directly
    - [ ] create correlation id
    - [ ] create UoW id
    - [ ] invoke hooks directly
    - [ ] invoke reset orchestration directly
    - [ ] enumerate reset tags
    - [ ] pass raw argv/options/arguments into UoW attributes
  - [ ] context reads inside command UoW are limited to:
    - [ ] `ContextKeys::CORRELATION_ID`
    - [ ] `ContextKeys::UOW_ID`
    - [ ] `ContextKeys::UOW_TYPE`
  - [ ] calls:
    - [ ] `KernelRuntimeInterface::runUnitOfWork(UnitOfWorkType::CLI, ...)`
    - [ ] attributes contain exactly `operation|output_format`
  - [ ] MUST NOT write UoW attributes into ContextStore directly

- [ ] `framework/packages/platform/cli/src/Diagnostics/CliErrorHandler.php`
  - [ ] implements `ErrorHandlerInterface`
  - [ ] canonical API:
    - [ ] `public function handle(Throwable $throwable, ?ErrorHandlingContext $context = null): ErrorDescriptor`
  - [ ] uses only safe `operation|correlationId` context fields when present
  - [ ] returned descriptor extensions are empty in the baseline
  - [ ] converts known CLI and Kernel port exceptions into format-neutral `ErrorDescriptor`
  - [ ] known mappings use only stable public codes and fixed safe messages
  - [ ] known mappings include:
    - [ ] `CliInputInvalidException`
    - [ ] `CliConfigInvalidException`
    - [ ] `InvalidCommandTagMetaException`
    - [ ] `CliCommandFailedException`
    - [ ] `KernelOpsFailedException`
  - [ ] unknown Throwable maps to:
    - [ ] code `CORETSIA_CLI_INTERNAL_ERROR`
    - [ ] fixed safe message
    - [ ] no raw extensions
  - [ ] MUST NOT copy:
    - [ ] Throwable message or class
    - [ ] stack trace
    - [ ] previous Throwable
    - [ ] path
    - [ ] argv or option values
  - [ ] performs no rendering, logging, redaction, or stream writes

- [ ] `framework/packages/platform/cli/src/Diagnostics/ExceptionRenderer.php`
  - [ ] consumes `ErrorDescriptor`, not raw Throwable
  - [ ] calls `OutputInterface::error($descriptor->code(), $descriptor->message())`
  - [ ] baseline CLI output does not render:
    - [ ] HTTP status
    - [ ] severity internals
    - [ ] arbitrary descriptor extensions
  - [ ] MUST NOT include Throwable messages, traces, previous exceptions, or paths

Redaction:
- [ ] CLI output MUST use `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`.
- [ ] CLI MUST NOT define package-local redaction engine/policy classes.
- [ ] CLI MAY define CLI-domain output formatting rules, but baseline sensitive data classification and redacted output generation belong to `platform/redaction`.

Built-in commands:
- [ ] `framework/packages/platform/cli/src/Command/DoctorCommand.php` — ultra-early checks (no kernel boot)
  - [ ] Stateless orchestrator; any per-run diagnostics collection MUST be local (if extracted into a service collector → that collector becomes resettable).
  - [ ] public constructor requires no services
  - [ ] performs only fixed allowlisted environment-capability checks
  - [ ] MUST NOT read application config, dotenv values, modules, Composer installed metadata, or generated artifacts
  - [ ] `NAME = doctor`
  - [ ] `GROUP = core`
  - [ ] `HIDDEN = false`
  - [ ] `ARGUMENTS = []`
  - [ ] `OPTIONS = []`
  - [ ] `SUMMARY = 'Check CLI bootstrap and runtime prerequisites.'`

- [ ] `framework/packages/platform/cli/src/Command/DebugModulesCommand.php`
  - [ ] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [ ] delegates exactly one `debugModules()` operation
  - [ ] passes exactly one explicit app target through `KernelOpsRequest`
  - [ ] MUST NOT resolve `ModulePlan`, read Composer metadata, or construct `ModuleResolution` directly
  - [ ] MUST NOT receive or render provider class names.
  - [ ] `KernelOpsInterface` and `OpsResult` MUST NOT expose provider instances, provider class lists, or raw Composer provider metadata.
  - [ ] stateless; no memoization of manifest, module plan, or provider plan across runs
  - [ ] `NAME = debug:modules`
  - [ ] `GROUP = debug`
  - [ ] `HIDDEN = false`
  - [ ] `ARGUMENTS = []`
  - [ ] `OPTIONS` contains exactly one descriptor:
    - [ ] `name = target`
    - [ ] `value = required`
    - [ ] `repeatable = false`
    - [ ] `summary = Application target: web, api, console, or worker.`
  - [ ] `SUMMARY = 'Show the resolved module plan for the configured target preset.'`
  - [ ] a handled error with null preset MUST NOT render a synthetic preset value

- [ ] `framework/packages/platform/cli/src/Command/ConfigValidateCommand.php`
  - [ ] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [ ] passes exactly one explicit app target through `KernelOpsRequest`
  - [ ] consumes only the returned safe `OpsResult`
  - [ ] MUST NOT resolve ConfigKernel or module services directly
  - [ ] stateless; no cached validation result
  - [ ] `NAME = config:validate`
  - [ ] `GROUP = config`
  - [ ] `HIDDEN = false`
  - [ ] `ARGUMENTS = []`
  - [ ] `OPTIONS` contains exactly one descriptor:
    - [ ] `name = target`
    - [ ] `value = required`
    - [ ] `repeatable = false`
    - [ ] `summary = Application target: web, api, console, or worker.`
  - [ ] `SUMMARY = 'Validate configuration for the configured target preset.'`
  - [ ] a handled error with null preset MUST NOT render a synthetic preset value

- [ ] `framework/packages/platform/cli/src/Command/ConfigDebugCommand.php`
  - [ ] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [ ] passes exactly one explicit app target through `KernelOpsRequest`
  - [ ] consumes only safe explain metadata returned in `OpsResult`
  - [ ] MUST NOT receive raw config or env values
  - [ ] MUST NOT run ConfigKernel or redaction over raw Kernel values locally
  - [ ] stateless; no cached explain traces
  - [ ] `NAME = config:debug`
  - [ ] `GROUP = config`
  - [ ] `HIDDEN = false`
  - [ ] `ARGUMENTS = []`
  - [ ] `OPTIONS` contains exactly one descriptor:
    - [ ] `name = target`
    - [ ] `value = required`
    - [ ] `repeatable = false`
    - [ ] `summary = Application target: web, api, console, or worker.`
  - [ ] `SUMMARY = 'Show safe configuration resolution diagnostics for the configured target preset.'`
  - [ ] a handled error with null preset MUST NOT render a synthetic preset value

- [ ] `framework/packages/platform/cli/src/Command/ConfigCompileCommand.php`
  - [ ] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [ ] passes exactly one explicit app target through `KernelOpsRequest`
  - [ ] MUST NOT call `ModulePlanResolver::resolve()` or `ModulePlanResolver::resolveResolution()`
  - [ ] MUST NOT read Composer metadata
  - [ ] MUST NOT resolve or construct `ContainerProviderPlan`
  - [ ] MUST NOT collect container definitions
  - [ ] MUST NOT invoke `ArtifactCompiler` directly
  - [ ] stateless; no CLI-owned module, provider, artifact, or cache state
  - [ ] constructs exactly one `KernelOpsRequest`
  - [ ] makes exactly one `compileConfig()` call
  - [ ] renders:
    - [ ] app target
    - [ ] effective preset only when non-null
    - [ ] published generation id
    - [ ] four canonical artifact identities and basenames
  - [ ] MUST NOT render synthetic `generations/current/<basename>` paths
  - [ ] MUST NOT read `current` after the Kernel call
  - [ ] MUST NOT verify or boot the published runtime locally
  - [ ] `ConfigCompileCommand::SUMMARY` = 'Compile and atomically publish one runtime artifact generation.'
  - [ ] `NAME = config:compile`
  - [ ] `GROUP = config`
  - [ ] `HIDDEN = false`
  - [ ] `ARGUMENTS = []`
  - [ ] `OPTIONS` contains exactly one descriptor:
    - [ ] `name = target`
    - [ ] `value = required`
    - [ ] `repeatable = false`
    - [ ] `summary = Application target: web, api, console, or worker.`
  - [ ] a handled error with null preset MUST NOT render a synthetic preset value

- [ ] `framework/packages/platform/cli/src/Command/ConfigHashCommand.php`
  - [ ] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [ ] passes exactly one explicit app target through `KernelOpsRequest`
  - [ ] MUST NOT resolve modules or calculate fingerprints locally
  - [ ] MUST NOT invoke `FingerprintCalculator` directly
  - [ ] renders only the safe expected generation-id result returned by Kernel
  - [ ] stateless; no manifest, module-plan, provider-plan, or fingerprint cache
  - [ ] renders the returned value as `generation_id`
  - [ ] help/summary MUST state that the command calculates the expected generation ID
  - [ ] MUST state that it does not write artifacts and does not inspect `current`
  - [ ] `ConfigHashCommand::SUMMARY` = 'Calculate the expected graph-bound artifact generation ID for the configured target preset.'
  - [ ] `NAME = config:hash`
  - [ ] `GROUP = config`
  - [ ] `HIDDEN = false`
  - [ ] `ARGUMENTS = []`
  - [ ] `OPTIONS` contains exactly one descriptor:
    - [ ] `name = target`
    - [ ] `value = required`
    - [ ] `repeatable = false`
    - [ ] `summary = Application target: web, api, console, or worker.`
  - [ ] a handled error with null preset MUST NOT render a synthetic preset value

- [ ] `framework/packages/platform/cli/src/Command/CacheVerifyCommand.php`
  - [ ] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [ ] passes exactly one explicit app target through `KernelOpsRequest`
  - [ ] MUST NOT call `ModulePlanResolver::resolve()` or `ModulePlanResolver::resolveResolution()`
  - [ ] MUST NOT read Composer metadata
  - [ ] MUST NOT construct or resolve `ContainerProviderPlan`
  - [ ] MUST NOT invoke `CacheVerifier` directly
  - [ ] renders Kernel-provided generation verification state:
    - [ ] `clean`
    - [ ] `dirty`
    - [ ] `invalid`
  - [ ] renders expected and nullable current generation ids
  - [ ] renders all four artifact statuses and safe reasons
  - [ ] renders byte counts when provided
  - [ ] MUST NOT receive or render filesystem paths from lower-level CacheVerifier results
  - [ ] stateless; no local manifest, module plan, provider plan, cache state, or last verification outcome
  - [ ] `CacheVerifyCommand::SUMMARY` = 'Verify the current artifact generation against expected inputs.'
  - [ ] `NAME = cache:verify`
  - [ ] `GROUP = cache`
  - [ ] `HIDDEN = false`
  - [ ] `ARGUMENTS = []`
  - [ ] `OPTIONS` contains exactly one descriptor:
    - [ ] `name = target`
    - [ ] `value = required`
    - [ ] `repeatable = false`
    - [ ] `summary = Application target: web, api, console, or worker.`
  - [ ] a handled error with null preset MUST NOT render a synthetic preset value

- [ ] `docs/adr/ADR-XXXX-cli-tag-first-command-catalog.md`
  - [ ] MUST capture:
    - [ ] tag-first discovery via `cli.command`
    - [ ] reserved built-in command names (`help`, `list`)
    - [ ] kernel ops consumption only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
    - [ ] deterministic output + redaction policy

Errors:
- [ ] `framework/packages/platform/cli/src/Exception/CliInputInvalidException.php`
  - [ ] code `CORETSIA_CLI_INPUT_INVALID`
  - [ ] code-first deterministic public message
  - [ ] exposes only stable safe reason tokens
  - [ ] used by `ArgvInputParser`, `CommandInputValidator`, catalog selection, and `KernelOpsRequestResolver`
  - [ ] MUST NOT contain argv values, option values, paths, or previous Throwable messages

- [ ] `framework/packages/platform/cli/src/Exception/CliBootstrapException.php`
  - [ ] code `CORETSIA_CLI_BOOTSTRAP_FAILED`
  - [ ] code-first deterministic public message
  - [ ] exposes only stable safe reason tokens
  - [ ] used after Composer autoload by `CliEntrypointPathsResolver`
  - [ ] MUST NOT contain launcher paths, autoload paths, skeleton paths, or previous Throwable messages

- [ ] `framework/packages/platform/cli/src/Exception/InvalidCommandTagMetaException.php`
  - [ ] code `CORETSIA_CLI_INVALID_COMMAND_META`
  - [ ] Stateless schema/registry violation exception
  - [ ] error details limited to names/serviceIds (no secrets/paths)

- [ ] `framework/packages/platform/cli/src/Exception/RedactionViolationException.php`
  - [ ] Stateless redaction policy violation exception
  - [ ] exposes only code `CORETSIA_CLI_REDACTION_VIOLATION`
  - [ ] exposes fixed reason `output-redaction-failed`
  - [ ] contains no raw value, hash, length, path, or previous Throwable message

- [ ] `framework/packages/platform/cli/src/Exception/CliOutputFormatException.php`
  - [ ] code `CORETSIA_CLI_OUTPUT_FORMAT_FAILED`
  - [ ] fixed reason `output-format-failed`
  - [ ] contains no rendered bytes, raw records, paths, or previous message

#### Deletes

Legacy production files:
- [ ] `framework/packages/platform/cli/src/Application.php`
  - [ ] remove config loading, root inference, FQCN registry, reflection, zero-argument command construction, and legacy dispatch
  - [ ] no compatibility wrapper or class alias remains

- [ ] `framework/packages/platform/cli/src/Input/CliInput.php`
  - [ ] replaced by `ArgvInput` and `ArgvInputParser`

- [ ] `framework/packages/platform/cli/src/Output/CliOutput.php`
  - [ ] replaced by buffer, formatters, redactor integration, and `ConsoleOutputWriter`

- [ ] `framework/packages/platform/cli/src/Output/TrackedOutput.php`
  - [ ] no mutable error-tracking decorator remains

- [ ] `framework/packages/platform/cli/src/Error/ErrorCodes.php`
  - [ ] error codes move to owning exception classes
  - [ ] no global CLI error-code registry remains

- [ ] `framework/packages/platform/cli/src/Exception/CliCommandClassMissingException.php`
- [ ] `framework/packages/platform/cli/src/Exception/CliCommandInvalidException.php`
- [ ] `framework/packages/platform/cli/src/Exception/CliException.php`
- [ ] `framework/packages/platform/cli/src/Exception/CliExceptionInterface.php`
  - [ ] legacy Phase-0 exception hierarchy is removed
  - [ ] no compatibility aliases remain

Legacy tests and fixtures:
- [ ] `framework/packages/platform/cli/tests/Contract/CliConfigSubtreeShapeAndMergeSemanticsTest.php`
- [ ] `framework/packages/platform/cli/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/cli/tests/Integration/ApplicationSkeletonDispatchIntegrationTest.php`
- [ ] `framework/packages/platform/cli/tests/Integration/CliBootHelpWorksWithEmptyCommandsTest.php`
- [ ] `framework/packages/platform/cli/tests/Integration/CliRejectsMissingCommandClassDeterministicallyTest.php`
- [ ] `framework/packages/platform/cli/tests/Integration/OutputRedactionDoesNotLeakTest.php`
- [ ] `framework/packages/platform/cli/tests/Fake/FakeWorkspaceSyncApplyCommand.php`
- [ ] `framework/packages/platform/cli/tests/Fake/FakeWorkspaceSyncDryRunCommand.php`
- [ ] `framework/packages/platform/cli/tests/Fixtures/LeakCommand.php`
- [ ] `framework/packages/platform/cli/tests/Fixtures/LeakCommand.prepend.php`
  - [ ] replaced by tag-backed catalog, output-pipeline, security, and external-package fixtures defined by this epic

#### Modifies

- [ ] `coretsia` — complete rewrite
  - [ ] repository-root wrapper
  - [ ] computes autoload and skeleton paths only from fixed repository-relative locations
  - [ ] sets explicit wrapper bootstrap variables
  - [ ] delegates to `framework/packages/platform/cli/bin/coretsia`
  - [ ] is independent of current working directory
  - [ ] performs no command parsing or rendering

- [ ] `framework/bin/coretsia` — complete rewrite
  - [ ] framework-root wrapper
  - [ ] computes autoload and skeleton paths only from fixed framework-relative locations
  - [ ] sets explicit wrapper bootstrap variables
  - [ ] delegates to the packaged CLI binary
  - [ ] is independent of current working directory
  - [ ] performs no command parsing or rendering

- [ ] `framework/packages/core/contracts/src/Cli/Input/InputInterface.php`
  - [ ] method signatures remain unchanged
  - [ ] document that `tokens()` exposes command-facing tokens only
  - [ ] global `format|color` options MUST NOT reach owner-package commands

- [ ] `framework/packages/platform/cli/src/Module/CliModule.php` — complete rewrite
  - [ ] follows the canonical module metadata shape
  - [ ] constants:
    - [ ] `MODULE_ID = 'platform.cli'`
    - [ ] `PACKAGE_ID = 'platform/cli'`
    - [ ] `COMPOSER_PACKAGE = 'coretsia/platform-cli'`
    - [ ] canonical `KIND`
    - [ ] `CONFIG_ROOT = 'cli'`
  - [ ] instance methods:
    - [ ] `id()`
    - [ ] `packageId()`
    - [ ] `composerPackage()`
    - [ ] `kind()`
    - [ ] `configRoot()`
    - [ ] `providers()`
  - [ ] `providers()` returns `CliServiceProvider::class`
  - [ ] no static-only Phase-0 module API remains
  - [ ] no config reads, command discovery, boot logic, or output logic

- [ ] `framework/packages/platform/cli/src/Exception/CliCommandFailedException.php` — complete rewrite
  - [ ] code `CORETSIA_CLI_COMMAND_FAILED`
  - [ ] code-first deterministic public message
  - [ ] exposes fixed safe reason token
  - [ ] previous throwable message is never included
  - [ ] no dependency on deleted `CliException` or `ErrorCodes`

- [ ] `framework/packages/platform/cli/src/Exception/CliConfigInvalidException.php` — complete rewrite
  - [ ] code `CORETSIA_CLI_CONFIG_INVALID`
  - [ ] code-first deterministic public message
  - [ ] exposes only stable safe reason tokens
  - [ ] used by `CliOutputPolicy`, `CliServiceFactory`, and `CommandOverrides`
  - [ ] MUST NOT include raw config values, dynamic override values, paths, or previous Throwable messages
  - [ ] no dependency on deleted `CliException` or `ErrorCodes`

- [ ] `framework/packages/platform/cli/src/Command/HelpCommand.php` — complete rewrite
  - [ ] existing Phase-0 implementation is replaced completely
  - [ ] receives `CommandCatalog`
  - [ ] renders general help from descriptors without resolving command services
  - [ ] renders command-specific arguments and options from metadata
  - [ ] unknown command fails deterministically
  - [ ] exposes canonical command constants
  - [ ] does not receive `list<string>` command names
  - [ ] contains no generic “help unavailable” Phase-0 fallback
  - [ ] does not reference Phase 0
  - [ ] `NAME = help`
  - [ ] `GROUP = core`
  - [ ] `HIDDEN = false`
  - [ ] `ARGUMENTS` contains exactly:
    - [ ] `name = command`
    - [ ] `summary = Command name.`
    - [ ] `required = false`
    - [ ] `variadic = false`
  - [ ] `OPTIONS = []`
  - [ ] `SUMMARY = 'Show general or command-specific help.'`

- [ ] `framework/packages/platform/cli/src/Command/ListCommand.php` — complete rewrite
  - [ ] existing Phase-0 implementation is replaced completely
  - [ ] receives `CommandCatalog`
  - [ ] renders descriptors without resolving command services
  - [ ] excludes hidden commands
  - [ ] groups deterministically while preserving canonical descriptor order within each group
  - [ ] uses command metadata summaries
  - [ ] exposes canonical command constants
  - [ ] does not receive `list<string>` command names
  - [ ] does not synthesize built-ins locally
  - [ ] does not reference Phase 0
  - [ ] `NAME = list`
  - [ ] `GROUP = core`
  - [ ] `HIDDEN = false`
  - [ ] `ARGUMENTS = []`
  - [ ] `OPTIONS = []`
  - [ ] `SUMMARY = 'List available commands.'`

- [ ] `framework/packages/platform/cli/src/Provider/CliServiceFactory.php`
  - [ ] stateless construction/wiring helper
  - [ ] MUST NOT keep caches, output buffers, terminal state, current command, or last result
  - [ ] reads CLI configuration only from the already-merged and validated `ConfigRepositoryInterface`
  - [ ] every config-dependent factory method reads the complete `cli` root through `cliConfigRoot()` exactly once
  - [ ] no factory method reads individual `cli.*` paths
  - [ ] canonical private helper:
    - [ ] `/** @return array<string, mixed> */`
    - [ ] `private static function cliConfigRoot(ConfigRepositoryInterface $config): array`
  - [ ] `cliConfigRoot()`:
    - [ ] requires `ConfigRepositoryInterface::has('cli')`
    - [ ] reads only `ConfigRepositoryInterface::get('cli')`
    - [ ] requires a string-keyed map
    - [ ] rejects a list
    - [ ] converts repository access failures into one deterministic safe container/config failure
    - [ ] performs no defaults fallback
    - [ ] performs no config file reads
  - [ ] builds from that validated root:
    - [ ] validated `cli.commands.overrides` map
    - [ ] `CliOutputPolicy`
    - [ ] `FormatResolver`
    - [ ] `ColorResolver`
    - [ ] `TableFormatter`
  - [ ] constructs one stateless `CommandOverrides` without config or catalog state
  - [ ] package defaults remain owned exclusively by `config/cli.php`
  - [ ] schema validation remains owned by `config/rules.php` and the existing ConfigKernel pipeline
  - [ ] `CliServiceFactory` performs only defensive shape assertions required for safe construction
  - [ ] MUST NOT read:
    - [ ] `cli.enabled`
    - [ ] `cli.uow.*`
    - [ ] `cli.redaction.*`
    - [ ] `cli.observability.*`
    - [ ] raw env values
    - [ ] raw argv
    - [ ] terminal state
  - [ ] constructs immutable `CliOutputPolicy`
  - [ ] passes the validated `cli.commands.overrides` map to `CommandCatalog`
  - [ ] `CommandCatalog` supplies that map to `CommandOverrides::apply()` exactly once
  - [ ] constructs `FormatResolver` from `CliOutputPolicy`
  - [ ] constructs `ColorResolver` from `CliOutputPolicy`
  - [ ] constructs `TableFormatter` with configured `maxWidth`
  - [ ] injects `SensitiveDataRedactorInterface` into `OutputFormatter`
  - [ ] injects canonical Kernel runtime, observability, context, logger, and stopwatch dependencies into `CommandRunner`
  - [ ] injects into `CommandRunner`:
    - [ ] `KernelRuntimeInterface`
    - [ ] `ContextAccessorInterface`
    - [ ] `TracerPortInterface`
    - [ ] `MeterPortInterface`
    - [ ] `LoggerInterface`
    - [ ] Foundation `Stopwatch`
  - [ ] injects `ContainerInterface` into `CommandRunner`
  - [ ] injects every constructor dependency declared by `CliApplication`
  - [ ] specifically injects:
    - [ ] `ArgvInputParser`
    - [ ] `CommandCatalog`
    - [ ] `CommandInputValidator`
    - [ ] `FormatResolver`
    - [ ] `ColorResolver`
    - [ ] `CommandRunner`
    - [ ] `CliErrorHandler` under its concrete service id
    - [ ] `ExceptionRenderer`
    - [ ] `OutputFormatter`
  - [ ] MUST NOT make command execution conditional on observability availability
  - [ ] MUST NOT silently invent defaults outside `config/cli.php`
  - [ ] wires the package-owned `CliErrorHandler`
  - [ ] MUST NOT instantiate logger, tracer, meter, or redactor implementations directly
  - [ ] MUST NOT resolve command services during construction
  - [ ] MUST NOT write stdout/stderr

- [ ] `framework/packages/platform/worker/src/Console/WorkerStartCommand.php`
  - [ ] remove `public const string MODE`
  - [ ] preserve `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
  - [ ] no dependency on `platform/cli`

- [ ] `framework/packages/platform/worker/src/Console/WorkerStopCommand.php`
  - [ ] remove `public const string MODE`
  - [ ] preserve `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
  - [ ] no dependency on `platform/cli`

- [ ] `framework/packages/platform/worker/src/Console/WorkerStatusCommand.php`
  - [ ] remove `public const string MODE`
  - [ ] preserve `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
  - [ ] no dependency on `platform/cli`

- [ ] `framework/packages/platform/worker/src/Provider/WorkerServiceProvider.php`
  - [ ] remove the `mode` argument from `commandMeta(...)`
  - [ ] remove the `mode` return-shape field
  - [ ] remove `'mode' => $mode`
  - [ ] stop passing `Worker*Command::MODE`
  - [ ] emitted command metadata contains exactly:
    - [ ] `name`
    - [ ] `summary`
    - [ ] `group`
    - [ ] `hidden`
    - [ ] `arguments`
    - [ ] `options`
  - [ ] retain `ReservedTags::CLI_COMMAND`
  - [ ] retain no compile-time dependency on `platform/cli`

- [ ] `framework/packages/core/contracts/src/Cli/Command/CommandInterface.php`
  - [ ] preserve existing methods:
    - [ ] `name(): string`
    - [ ] `run(InputInterface $input, OutputInterface $output): int`
  - [ ] tagged command classes MUST expose:
    - [ ] `public const string NAME`
    - [ ] `public const string SUMMARY`
    - [ ] `public const string GROUP`
    - [ ] `public const bool HIDDEN`
    - [ ] `public const array ARGUMENTS`
    - [ ] `public const array OPTIONS`
  - [ ] remove any documentation requirement for `MODE`
  - [ ] `name()` MUST return `self::NAME`
  - [ ] portable process exit code range is `0..255`
  - [ ] contract remains independent of `platform/cli`

- [ ] `framework/packages/platform/cli/composer.json` — complete rewrite of Phase-0 metadata
  - [ ] remove description claims:
    - [ ] `config-based command registry`
    - [ ] `kernel-free in Phase 0`
  - [ ] require direct production dependencies used by source:
    - [ ] `php: ^8.4`
    - [ ] `coretsia/core-contracts: ^0.5.0`
    - [ ] `coretsia/core-foundation: ^0.5.0`
    - [ ] `coretsia/core-kernel: ^0.5.0`
    - [ ] `psr/container: ^2.0`
    - [ ] `psr/log: ^3.0`
  - [ ] declare `"bin": ["bin/coretsia"]`
  - [ ] preserve PSR-4 package namespace
  - [ ] preserve:
    - [ ] `moduleId`
    - [ ] `moduleClass`
    - [ ] `providers`
    - [ ] `defaultsConfigPath`
  - [ ] set `extra.coretsia.requires` exactly to:
    - [ ] `core.kernel`
    - [ ] `platform.redaction`
  - [ ] MUST NOT require:
    - [ ] `platform/worker`
    - [ ] migration/database packages
    - [ ] integrations solely for command discovery
    - [ ] a concrete redaction implementation solely to use the contracts port

- [ ] `framework/packages/platform/cli/src/Provider/CliServiceProvider.php`
  - [ ] existing placeholder Phase-0 provider is replaced completely
  - [ ] legacy `id()` and static `factories()` placeholder API are removed
  - [ ] implements:
    - [ ] `ServiceProviderInterface`
    - [ ] `ContainerDefinitionProviderInterface`
  - [ ] follows the existing Kernel provider split:
    - [ ] source-host-only wiring remains in `register()`
    - [ ] runtime-representable generic CLI wiring is declared in `define()`
  - [ ] `register()`:
    - [ ] registers `KernelOpsRequestResolver` as a source-host-only stateless service
    - [ ] registers the six Kernel operation command services with direct constructor dependencies on `KernelOpsInterface` and `KernelOpsRequestResolver`
    - [ ] registers the six Kernel operation `cli.command` tags
    - [ ] MUST NOT duplicate generic CLI services declared by `define()`
    - [ ] delegates the runtime-representable contribution through:
      - [ ] `$builder->registerDefinitionProvider($this)`
  - [ ] `define()` is the single definition source for generic CLI infrastructure:
    - [ ] `CliServiceFactory`
    - [ ] parser and validated input services
    - [ ] `CommandCatalog`
    - [ ] output policy, resolvers, and formatters
    - [ ] `CommandRunner`
    - [ ] `CliApplication`
    - [ ] `CliErrorHandler`
    - [ ] `ExceptionRenderer`
    - [ ] `HelpCommand`
    - [ ] `ListCommand`
    - [ ] `DoctorCommand`
    - [ ] their generic built-in `cli.command` tags
  - [ ] `CommandOutputBuffer` MUST NOT be registered by `register()` or `define()`
  - [ ] `CliApplication` creates exactly one invocation-local `CommandOutputBuffer` per `run()` call
  - [ ] `define()` MUST NOT reference or require:
    - [ ] `KernelOpsInterface`
    - [ ] `ConfigValidateCommand`
    - [ ] `ConfigDebugCommand`
    - [ ] `ConfigCompileCommand`
    - [ ] `ConfigHashCommand`
    - [ ] `CacheVerifyCommand`
    - [ ] `DebugModulesCommand`
    - [ ] `KernelOpsHostInput`
    - [ ] `KernelOpsHostBooter`
  - [ ] generic CLI infrastructure MAY enter canonical runtime definitions
  - [ ] source-host-only Kernel operation commands MUST NOT enter canonical runtime definitions
  - [ ] no second provider-discovery or imperative-provider planning mechanism is introduced
  - [ ] MUST NOT import Kernel host booters or concrete Kernel Ops implementations
  - [ ] consumes Kernel operations only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - [ ] MUST register app/runner/catalog/services
  - [ ] MUST NOT bind or implement that interface inside `platform/cli`
  - [ ] MUST register built-in command services
  - [ ] MUST tag all built-in commands with `ReservedTags::CLI_COMMAND`
  - [ ] MUST tag built-in commands using command class constants:
    - [ ] `NAME`
    - [ ] `SUMMARY`
    - [ ] `GROUP`
    - [ ] `HIDDEN`
    - [ ] `ARGUMENTS`
    - [ ] `OPTIONS`
  - [ ] MUST NOT invent built-in command names as unrelated string literals
  - [ ] MUST NOT build `CommandCatalog` during provider registration
  - [ ] MUST NOT instantiate command services during provider registration
  - [ ] MUST NOT parse CLI input during provider registration
  - [ ] MUST NOT inspect runtime command options during provider registration
  - [ ] MUST NOT use filesystem scanning
  - [ ] MUST NOT read `cli.commands` registry list
  - [ ] MUST provide reserved built-in command service id map to catalog/factory:
    - [ ] `help`
    - [ ] `list`
  - [ ] declares required source-host services:
    - [ ] `KernelOpsInterface`
    - [ ] `KernelRuntimeInterface`
    - [ ] `ContextAccessorInterface`
    - [ ] `TracerPortInterface`
    - [ ] `MeterPortInterface`
    - [ ] `LoggerInterface`
    - [ ] `SensitiveDataRedactorInterface`
    - [ ] `ConfigRepositoryInterface`
    - [ ] passes the same validated console-host `ConfigRepositoryInterface` to `CliServiceFactory`
    - [ ] MUST NOT construct another config repository
    - [ ] MUST NOT load `config/cli.php` or `config/rules.php` directly
    - [ ] MUST NOT expose CLI config values through command input
    - [ ] `Psr\Container\ContainerInterface` alias for the built source container
    - [ ] `TagRegistry`
    - [ ] Foundation `Stopwatch`
  - [ ] registers `CliOutputPolicy`
  - [ ] registers `FormatResolver`
  - [ ] registers `ColorResolver`
  - [ ] registers fixed ANSI decorator
  - [ ] registers formatter services with explicit dependencies
  - [ ] defines `CliErrorHandler` under its concrete service id through `define()`
  - [ ] defines `ExceptionRenderer` through `define()`
  - [ ] MUST NOT bind the global `ErrorHandlerInterface` service id to a CLI-specific implementation
  - [ ] source-host wiring contains no config reads during provider registration
  - [ ] all config reads remain in `CliServiceFactory`

- [ ] `framework/packages/platform/cli/config/cli.php`
  - [ ] returns the `cli` subtree only
  - [ ] MUST NOT repeat the root as `['cli' => ...]`
  - [ ] contains only deterministic scalar/map/list defaults
  - [ ] contains no closures, objects, resources, floats, env reads, terminal detection, or runtime values
  - [ ] canonical dot keys:
    - [ ] `cli.commands.overrides` = []
      - [ ] this node permits dynamic map keys structurally
      - [ ] `additionalKeys = true` applies only at this node
      - [ ] every dynamic key and value is validated later by `CommandOverrides`
    - [ ] `cli.output.format_default` = "adaptive"
    - [ ] `cli.output.adaptive.interactive` = "table"
    - [ ] `cli.output.adaptive.non_interactive` = "plain"
    - [ ] `cli.output.color_default` = "auto"
    - [ ] `cli.output.table.max_width` = 120
  - [ ] MUST NOT contain:
    - [ ] `cli.enabled`
    - [ ] `cli.commands` as a registry list
    - [ ] `cli.mode.*`
    - [ ] `cli.uow.*`
    - [ ] `cli.redaction.*`
    - [ ] `cli.output.redaction.*`
    - [ ] `cli.observability.*`
    - [ ] `cli.output.colors.*`
    - [ ] `cli.output.palette.*`

- [ ] `framework/packages/platform/cli/config/rules.php`
  - [ ] returns a plain declarative ruleset array
  - [ ] validates the `cli` subtree with `additionalKeys = false`
  - [ ] `cli.commands`
    - [ ] required map
    - [ ] `additionalKeys = false`
    - [ ] exact key: `overrides`
  - [ ] `cli.commands.overrides`
    - [ ] required map
    - [ ] `additionalKeys = true`
    - [ ] `ConfigValidator` validates only that the value is a map
    - [ ] dynamic map keys and map values are validated by `CommandOverrides`
    - [ ] rules MUST NOT pretend to validate arbitrary command-name keys through undeclared static `keys`
  - [ ] `cli.output`
    - [ ] required map
    - [ ] `additionalKeys = false`
    - [ ] exact keys:
      - [ ] `format_default`
      - [ ] `adaptive`
      - [ ] `color_default`
      - [ ] `table`
  - [ ] `cli.output.format_default`
    - [ ] required string
    - [ ] `allowedValues = adaptive|json|table|plain`
  - [ ] `cli.output.adaptive`
    - [ ] required map
    - [ ] `additionalKeys = false`
    - [ ] exact keys: `interactive|non_interactive`
  - [ ] `cli.output.adaptive.interactive`
    - [ ] required string
    - [ ] `allowedValues = json|table|plain`
  - [ ] `cli.output.adaptive.non_interactive`
    - [ ] required string
    - [ ] `allowedValues = json|table|plain`
  - [ ] `cli.output.color_default`
    - [ ] required string
    - [ ] `allowedValues = auto|always|never`
  - [ ] `cli.output.table`
    - [ ] required map
    - [ ] `additionalKeys = false`
    - [ ] exact key: `max_width`
  - [ ] `cli.output.table.max_width`
    - [ ] required int
    - [ ] `min = 40`
    - [ ] `max = 240`
  - [ ] schema rejects:
    - [ ] registry-list `cli.commands`
    - [ ] `cli.enabled`
    - [ ] `cli.mode.*`
    - [ ] `cli.uow.*`
    - [ ] `cli.redaction.*`
    - [ ] `cli.output.redaction.*`
    - [ ] `cli.observability.*`
    - [ ] color palettes or arbitrary ANSI values
    - [ ] unknown static keys at every schema-owned level
    - [ ] dynamic keys under `cli.commands.overrides` are the only structural exception

- [ ] `docs/ssot/tags.md`
  - [ ] define that every `cli.command` service id is the exact command class FQCN
  - [ ] define lazy command-constant validation without service resolution
  - [ ] preserve owner `platform/cli` for `cli.command`
  - [ ] define exact metadata keys:
    - [ ] `name`
    - [ ] `summary`
    - [ ] `group`
    - [ ] `hidden`
    - [ ] `arguments`
    - [ ] `options`
  - [ ] forbid:
    - [ ] `priority`
    - [ ] `mode`
    - [ ] unknown metadata keys
  - [ ] define deterministic TagRegistry order
  - [ ] define duplicate command-name failure
  - [ ] define reserved external names `help|list`
  - [ ] document that command-owner packages depend only on contracts-level CLI ports
  - [ ] `cli.command` registrations use actual tag priority `0`
  - [ ] non-zero priority is invalid independently of metadata contents

- [ ] `docs/ssot/observability.md`
  - [ ] register span `cli.command`
  - [ ] register counter `cli.command_total`
  - [ ] register observation `cli.command_duration_ms`
  - [ ] owner is `platform/cli`
  - [ ] labels are exactly `operation|outcome`
  - [ ] `operation` is the canonical command name from the selected descriptor
  - [ ] allowed outcomes are `success|failure`
  - [ ] `format|exit_code|app_target|preset|correlation_id|uow_id` are forbidden metric labels
  - [ ] `format|exit_code` may be bounded span attributes
  - [ ] raw arguments, options, output records, paths, endpoints, config, and payloads are forbidden

- [ ] `framework/packages/platform/cli/README.md` MUST include:
  - [ ] existing README is replaced completely
  - [ ] remove historical descriptions:
    - [ ] Phase 0
    - [ ] kernel-free CLI
    - [ ] config-based command registry
    - [ ] FQCN command lists
    - [ ] zero-argument command constructors
    - [ ] package-local redaction
    - [ ] monorepo-only launcher layout
  - [ ] Errors and exit codes
  - [ ] Security and redaction
  - [ ] Kernel target and configured-preset behavior
  - [ ] Determinism
  - [ ] Providing commands from another package
  - [ ] Owner-package arguments, options, services, and domain validation
  - [ ] Configuration:
    - [ ] exact default shape
    - [ ] config consumers
    - [ ] global-option precedence
    - [ ] adaptive format behavior
    - [ ] color behavior
    - [ ] no configurable redaction/UoW/observability toggles
  - [ ] Context and UoW:
    - [ ] one UoW per normal command
    - [ ] doctor exception
    - [ ] context read/write ownership
    - [ ] reset ownership
  - [ ] Observability:
    - [ ] span and metric names
    - [ ] allowed labels and attributes
    - [ ] failure isolation
  - [ ] Colors:
    - [ ] `auto|always|never`
    - [ ] JSON is ANSI-free
    - [ ] fixed internal semantic palette
    - [ ] no arbitrary configured ANSI codes

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-cli-tag-first-command-catalog.md`

- [ ] `framework/packages/platform/cli/tests/Contract/CommandsDoNotWriteToStdoutTest.php` — complete rewrite
  - [ ] scans every production command under `src/Command`
  - [ ] rejects:
    - [ ] `echo`
    - [ ] `print`
    - [ ] `printf`
    - [ ] `var_dump`
    - [ ] `print_r`
    - [ ] `error_log`
    - [ ] direct `STDOUT|STDERR`
    - [ ] `php://stdout|php://stderr|php://output`
  - [ ] excludes tests and fixtures
  - [ ] does not forbid writes inside the explicit `ConsoleOutputWriter`

### Cross-cutting (MUST)

#### Context & UoW

- [ ] Normal command UoW:
  - [ ] every normal command executes through exactly one `KernelRuntimeInterface` UoW
  - [ ] canonical UoW type is `cli`
  - [ ] UoW begins after descriptor validation and before lazy command resolution
  - [ ] command resolution, command-name verification, execution, and exit-code validation occur inside the same UoW callback
  - [ ] one command invocation MUST NOT create nested CLI UoWs
  - [ ] `doctor` is the only pre-host and pre-UoW command path
- [ ] Safe UoW attributes supplied by `CommandRunner`:
  - [ ] canonical command operation id
  - [ ] effective CLI output format
  - [ ] no raw argv
  - [ ] no positional argument values
  - [ ] no option values
  - [ ] no output records
  - [ ] no filesystem paths
  - [ ] no endpoints
  - [ ] no payloads or secrets
- [ ] Context writes:
  - [ ] platform/cli performs no direct `ContextStore` writes
  - [ ] `CommandRunner` passes safe UoW inputs to `KernelRuntimeInterface`
  - [ ] KernelRuntime owns base ContextStore writes:
    - [ ] `ContextKeys::CORRELATION_ID`
    - [ ] `ContextKeys::UOW_ID`
    - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `cli_output_format` is not introduced as a ContextStore key
  - [ ] `CommandRunner` passes the following safe UoW attributes:
    - [ ] `operation` = canonical command name
    - [ ] `output_format` = resolved `json|table|plain`
  - [ ] UoW attributes remain lifecycle/hook payload data and MUST NOT be written into ContextStore by platform/cli
- [ ] Context reads:
  - [ ] allowed only through `ContextAccessorInterface`
  - [ ] allowed keys:
    - [ ] `ContextKeys::CORRELATION_ID`
    - [ ] `ContextKeys::UOW_ID`
    - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `output_format` is read from invocation/UoW input, not from ContextAccessorInterface
  - [ ] used only for safe observability and diagnostics
  - [ ] commands and formatters MUST NOT require these values to produce their domain result
- [ ] Reset discipline:
  - [ ] reset is triggered only by the canonical KernelRuntime lifecycle
  - [ ] platform/cli MUST NOT enumerate `kernel.reset`
  - [ ] platform/cli MUST NOT call `ResetOrchestrator` directly
  - [ ] platform/cli introduces no shared mutable service in the baseline
  - [ ] `CommandOutputBuffer` is invocation-local and is not a container singleton
  - [ ] immutable catalog, descriptor, and output-policy objects do not require reset
  - [ ] any future shared mutable service must implement `ResetInterface` and satisfy canonical stateful-service policy

#### Observability (policy-compliant)

- [ ] Ownership:
  - [ ] `CommandRunner` owns CLI command execution observability
  - [ ] `KernelOpsFacade` owns Kernel operation observability
  - [ ] built-in Kernel operation commands and CLI transport services MUST NOT emit duplicate Kernel-operation spans or metrics
  - [ ] formatters and writers MUST NOT emit command lifecycle metrics
- [ ] Command span:
  - [ ] name: `cli.command`
  - [ ] exactly one span per normal command execution
  - [ ] safe attributes:
    - [ ] `operation`
    - [ ] `outcome`
    - [ ] `format`
    - [ ] `exit_code`
  - [ ] `operation` is the canonical command name
  - [ ] `exit_code` is a span attribute only
  - [ ] no arguments, options, paths, endpoints, payloads, or config values
- [ ] Command metrics:
  - [ ] `cli.command_total`
    - [ ] labels: `operation|outcome`
  - [ ] `cli.command_duration_ms`
    - [ ] labels: `operation|outcome`
  - [ ] duration measured through Foundation `Stopwatch`
  - [ ] metric values use integer milliseconds
  - [ ] `format|exit_code|app_target|preset|correlation_id|uow_id` are forbidden metric labels
- [ ] Canonical outcomes:
  - [ ] thrown exception uses canonical process exit code `1` for span and log attributes
  - [ ] exit code `0` → `success`
  - [ ] non-zero returned exit code → `failure`
  - [ ] thrown exception → `failure`
- [ ] Logs:
  - [ ] one safe command completion/failure summary
  - [ ] safe fields:
    - [ ] `operation`
    - [ ] `outcome`
    - [ ] `exit_code`
  - [ ] MAY include `correlation_id|uow_id`
  - [ ] MUST NOT include raw argv, arguments, option values, command output, paths, endpoints, payloads, env/config values, tokens, exception stack traces, or previous throwable messages
- [ ] Failure isolation:
  - [ ] tracer failure MUST NOT alter command exit code
  - [ ] meter failure MUST NOT alter command exit code
  - [ ] logger failure MUST NOT alter command exit code
  - [ ] observability failures MUST NOT replace the primary command exception
- [ ] Doctor:
  - [ ] ultra-early doctor does not require tracer, meter, logger, context, or UoW services
  - [ ] doctor emits no normal `cli.command` runtime span
  - [ ] doctor diagnostics are safe by construction through a fixed allowlist
  - [ ] ultra-early doctor MUST NOT invoke `SensitiveDataRedactorInterface`

### Security / Redaction (MUST)

- [ ] Redaction is mandatory:
  - [ ] every normal command output passes through mandatory defense-in-depth redaction
  - [ ] ultra-early doctor is the sole exception and is safe by construction
  - [ ] no config key disables it
  - [ ] no global option disables it
  - [ ] no owner-package command can bypass final CLI defense-in-depth redaction
  - [ ] `OutputFormatter` always receives `SensitiveDataRedactorInterface`
- [ ] CLI MUST NOT leak:
  - [ ] dotenv or env values
  - [ ] raw config values
  - [ ] tokens
  - [ ] authorization headers
  - [ ] cookies or session ids
  - [ ] raw SQL
  - [ ] payloads
  - [ ] filesystem paths unless explicitly owner-approved output contract permits a safe relative path
  - [ ] exception stack traces by default
- [ ] Allowed diagnostics:
  - [ ] stable reason tokens
  - [ ] bounded operation ids
  - [ ] integer counts and lengths
  - [ ] safe hashes
  - [ ] correlation and UoW ids where policy permits
- [ ] Color safety:
  - [ ] config cannot contain arbitrary ANSI escape sequences
  - [ ] JSON output is always ANSI-free
  - [ ] owner-package output values MUST NOT be interpreted as ANSI control sequences

### Tests (MUST)

- Test fixtures:
  - [ ] `framework/packages/platform/cli/tests/Fixture/ExternalCommand/ExternalOwnerService.php`
  - [ ] `framework/packages/platform/cli/tests/Fixture/ExternalCommand/ExternalModeCommand.php`
    - [ ] implements `CommandInterface`
    - [ ] declares owner-domain `mode` option
    - [ ] depends on `ExternalOwnerService`

  - [ ] `framework/packages/platform/cli/tests/Fixture/ExternalCommand/ExternalCommandServiceProvider.php`
    - [ ] contributes the command only through `cli.command`
    - [ ] references command constants

  - [ ] `framework/packages/platform/cli/tests/Fixture/ExternalCommand/ReservedHelpCommand.php`
    - [ ] contributes external name `help` for deterministic collision testing

- Unit:
  - [ ] `framework/packages/platform/cli/tests/Unit/ColorResolverDisablesAutoWhenStderrIsRedirectedTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/ParsedCliInvocationTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CliErrorHandlerTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CommandCatalogRejectsNonZeroTagPriorityTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CommandOutputBufferRejectsAnsiAndControlBytesTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CliEntrypointPathsResolverTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CommandRunnerResolvesOnlySelectedServiceTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CommandTagSchemaTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CommandCatalogDeterminismTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/ArgvInputParserTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/TableFormatterHonorsConfiguredMaxWidthTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CommandRunnerObservabilityFailureIsolationTest.php`

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandCatalogRejectsNonClassServiceIdTest.php`
    - [ ] a non-class service id hard-fails before descriptor construction
    - [ ] no command service is resolved

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandCatalogRejectsMetadataConstantMismatchTest.php`
    - [ ] tag metadata differing from command constants hard-fails
    - [ ] command constants are read without service construction

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandOutputBufferDiscardTest.php`
    - [ ] clears all pre-error records
    - [ ] remains writable before finalization
    - [ ] rejects discard after finalization

  - [ ] `framework/packages/platform/cli/tests/Unit/KernelOpsRequestResolverTest.php`
    - [ ] consumes only the already validated `InputInterface`
    - [ ] accepts exactly one non-empty scalar `target`
    - [ ] has no `CommandDescriptor` dependency
    - [ ] does not validate canonical Kernel target values

  - [ ] `framework/packages/platform/cli/tests/Unit/CliServiceFactoryReadsValidatedCliRootTest.php`
    - [ ] every config-dependent factory method reads only the complete `cli` root
    - [ ] each config-dependent factory method calls `ConfigRepositoryInterface::get('cli')` exactly once
    - [ ] no factory method reads an individual `cli.*` path

  - [ ] `framework/packages/platform/cli/tests/Unit/OutputFormatterUsesSensitiveDataRedactorTest.php`
    - [ ] formatter receives `SensitiveDataRedactorInterface`
    - [ ] formatter does not instantiate or resolve a concrete redactor
    - [ ] no CLI-local classifier, policy, hasher, or pattern registry is constructed
    - [ ] normalized records are passed through the shared port exactly once
    - [ ] raw sensitive fixture values do not reach rendered output
    - [ ] redacted maps are recursively re-sorted before concrete formatting

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandTagSchemaRejectsPriorityMetadataKeyTest.php`
    - [ ] `priority` key hard-fails deterministically

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandTagSchemaRejectsUnknownKeysTest.php`
    - [ ] unknown metadata keys hard-fail deterministically

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandTagSchemaValidatesNameRegexTest.php`
    - [ ] invalid command names hard-fail deterministically

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandCatalogDoesNotInstantiateCommandsForListTest.php`
    - [ ] catalog can build descriptors from tag metadata without resolving command services

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandCatalogRejectsDuplicateNamesTest.php`
    - [ ] duplicate command names hard-fail deterministically

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandCatalogRejectsReservedExternalNamesTest.php`
    - [ ] external `help` hard-fails
    - [ ] external `list` hard-fails
    - [ ] built-in `help` and `list` are allowed only for platform/cli built-in service ids

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandRunnerValidatesCommandNameMatchesDescriptorTest.php`
    - [ ] descriptor name and command `name()` mismatch hard-fails with `InvalidCommandTagMetaException`

  - [ ] `framework/packages/platform/cli/tests/Unit/CliOutputPolicyTest.php`
    - [ ] accepts complete default config
    - [ ] rejects unsupported format token
    - [ ] rejects unsupported color token
    - [ ] rejects invalid table width

  - [ ] `framework/packages/platform/cli/tests/Unit/FormatResolverTest.php`
    - [ ] explicit format overrides configured default
    - [ ] adaptive interactive resolves to configured interactive format
    - [ ] adaptive non-interactive resolves to configured non-interactive format
    - [ ] performs no CI env detection

  - [ ] `framework/packages/platform/cli/tests/Unit/ColorResolverTest.php`
    - [ ] explicit color overrides configured default
    - [ ] auto requires both interactive output and ANSI support
    - [ ] never disables color
    - [ ] always enables color for text formats
    - [ ] JSON always disables color

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandRunnerObservabilityTest.php`
    - [ ] emits one span
    - [ ] emits total and duration metrics
    - [ ] labels only `operation|outcome`
    - [ ] output format and exit code are not metric labels
    - [ ] thrown command failure records `exit_code = 1`

- Contract:
  - [ ] `framework/packages/platform/cli/tests/Contract/CliDoesNotReferenceArtifactRuntimeOrGenerationInternalsContractTest.php`
  - [ ] `framework/packages/platform/cli/tests/Contract/CliModuleMetadataContractTest.php`
  - [ ] `framework/packages/platform/cli/tests/Contract/JsonOutputNeverContainsAnsiContractTest.php`

  - [ ] `framework/packages/platform/cli/tests/Contract/CliDoesNotBindGlobalErrorHandlerPortContractTest.php`
    - [ ] `CliErrorHandler` implements `ErrorHandlerInterface`
    - [ ] `CliApplication` receives `CliErrorHandler` under its concrete service id
    - [ ] `CliServiceProvider` does not alias `ErrorHandlerInterface`

  - [ ] `framework/packages/platform/cli/tests/Contract/CliCommandOutputBufferIsInvocationLocalContractTest.php`
    - [ ] `CommandOutputBuffer` is absent from source-host service registration
    - [ ] `CommandOutputBuffer` is absent from canonical runtime definitions
    - [ ] `CliApplication` creates exactly one buffer per invocation
    - [ ] no buffer state is retained between `CliApplication::run()` calls
    - [ ] the same invocation-local buffer is cleared and reused for error rendering

  - [ ] `framework/packages/platform/cli/tests/Contract/CliDoesNotImplementConfigPipelineContractTest.php`
    - [ ] no direct package config-file reads
    - [ ] no CLI-local loader, merger, validator, directive processor, or repository
    - [ ] only `ConfigRepositoryInterface` is consumed

  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerCommandMetadataConstantsTest.php`
    - [ ] remove all `MODE` assertions
    - [ ] assert exactly `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
    - [ ] assert arrays and scalar metadata types

  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerServiceProviderCliCommandTaggingTest.php`
    - [ ] remove `mode` from expected metadata shape
    - [ ] assert exact six-key metadata
    - [ ] preserve `cli.command` tag assertions

  - [ ] `framework/packages/platform/cli/tests/Contract/LegacyPhase0CliFilesAreRemovedContractTest.php`
    - [ ] asserts every file listed under `Deletes` is absent
    - [ ] asserts no production reference to:
      - [ ] `cli.commands` registry list
      - [ ] `new $fqcn`
      - [ ] command reflection
      - [ ] zero-argument command policy
      - [ ] `CliOutput`
      - [ ] `TrackedOutput`
      - [ ] `ErrorCodes`
      - [ ] `RedactionEngine`
      - [ ] `RedactionPolicy`
      - [ ] Phase 0

  - [ ] `framework/packages/platform/cli/tests/Contract/CliComposerRuntimeDependenciesContractTest.php`
    - [ ] correct direct dependencies
    - [ ] bin declared
    - [ ] no command-owner package dependency solely for discovery

  - [ ] `framework/packages/platform/cli/tests/Contract/CliHasSingleProductionOutputSinkContractTest.php`
    - [ ] after Composer autoload succeeds, only `ConsoleOutputWriter.php` writes stdout/stderr
    - [ ] binary may construct and pass stdout/stderr streams
    - [ ] binary may write directly to stderr only for the fixed pre-autoload failure
    - [ ] `TerminalCapabilitiesDetector` may inspect streams but never writes them
    - [ ] no other production class references output sinks

  - [ ] `framework/packages/platform/cli/tests/Contract/JsonOutputSchemaContractTest.php`
    - [ ] MUST load schema from `framework/packages/platform/cli/resources/schema/cli_output@1.json` (no inline schema duplication)

  - [ ] `framework/packages/platform/cli/tests/Contract/CliDoesNotReferenceKernelCompileInternalsContractTest.php`
    - [ ] scans `framework/packages/platform/cli/src`
    - [ ] rejects imports and FQCN references to:
      - [ ] `ModulePlanResolver`
      - [ ] `ModuleResolution`
      - [ ] `ContainerProviderPlan`
      - [ ] `ContainerProviderPlanResolver`
      - [ ] `ManifestReaderInterface`
      - [ ] `ComposerManifestReader`
      - [ ] `ArtifactCompiler`
      - [ ] `FingerprintCalculator`
      - [ ] `CacheVerifier`
    - [ ] allows `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` throughout platform/cli Kernel-operation adapters
    - [ ] allows `KernelOpsHostInput` and `KernelOpsHostBooter` only in `src/Bootstrap/CliHostBootstrap.php`
    - [ ] rejects every other `Coretsia\Kernel\Ops\*` reference

  - [ ] `framework/packages/platform/cli/tests/Contract/CliServiceProviderSeparatesSourceOnlyKernelOpsWiringContractTest.php`
    - [ ] `CliServiceProvider` implements both provider interfaces
    - [ ] source container contains `KernelOpsRequestResolver`
    - [ ] source container contains all six Kernel operation commands
    - [ ] each Kernel operation command receives `KernelOpsInterface` through constructor injection
    - [ ] no command imports or resolves `Coretsia\Kernel\Ops\KernelOpsFacade`
    - [ ] source container contains their `cli.command` tags
    - [ ] `define()` contains generic CLI infrastructure
    - [ ] `define()` contains `CliErrorHandler` and `ExceptionRenderer`
    - [ ] `define()` contains no `KernelOpsInterface` and no Kernel operation command services or tags
    - [ ] canonical runtime definitions contain no Kernel operation command services or tags
    - [ ] canonical runtime definitions contain no `KernelOpsRequestResolver`
    - [ ] canonical runtime definitions contain no `KernelOpsHostInput` or `KernelOpsHostBooter`

  - [ ] `framework/packages/platform/cli/tests/Contract/CliConfigSubtreeShapeContractTest.php`
    - [ ] config returns subtree only
    - [ ] no root repetition
    - [ ] exact default keys
    - [ ] no closures, objects, resources, floats, or env reads

  - [ ] `framework/packages/platform/cli/tests/Contract/CliConfigRulesCoverAllDefaultsContractTest.php`
    - [ ] every default key has a rule
    - [ ] no rule-owned key is missing from defaults
    - [ ] unknown keys are rejected

  - [ ] `framework/packages/platform/cli/tests/Contract/CliRedactionCannotBeDisabledContractTest.php`
    - [ ] no redaction enable/disable config key
    - [ ] no redaction bypass option
    - [ ] OutputFormatter requires `SensitiveDataRedactorInterface`

  - [ ] `framework/packages/platform/cli/tests/Contract/CliHasNoPackageLocalRedactionImplementationContractTest.php`
    - [ ] `framework/packages/platform/cli/src/Redaction/` does not exist
    - [ ] `framework/packages/platform/cli/src/Output/Redaction/` does not exist
    - [ ] `RedactionEngine.php` is absent
    - [ ] `RedactionPolicy.php` is absent
    - [ ] production source defines no CLI-local:
      - [ ] sensitive-key classifier
      - [ ] sensitive-value classifier
      - [ ] redaction policy
      - [ ] redaction hasher
      - [ ] pattern registry
    - [ ] `OutputFormatter` depends only on `SensitiveDataRedactorInterface`
    - [ ] no platform/cli class imports or instantiates `DefaultSensitiveDataRedactor`

  - [ ] `framework/packages/platform/cli/tests/Contract/CliDoesNotWriteContextOrResetDirectlyContractTest.php`
    - [ ] no direct ContextStore writes
    - [ ] no ResetOrchestrator dependency
    - [ ] no reset tag enumeration

- Integration:
  - [ ] `framework/packages/platform/cli/tests/Integration/CoretsiaWrappersAreCwdIndependentTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/PreAutoloadFailureIsFixedAndPathSafeTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/NormalCommandExecutesExactlyOneKernelUowTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/DoctorDoesNotEnterKernelUowTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/UltraEarlyDoctorUsesSafeFixedOutputPipelineTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CliGlobalColorOptionIsNotPassedToPackageCommandTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/JsonFormatSuppressesAnsiWhenColorAlwaysTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/NonInteractiveAdaptiveOutputUsesPlainFormatTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/ExternalPackageCommandExitCodeIsPreservedTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/KernelCommandsRequireExplicitTargetTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/ConfigCompileRendersGenerationAwareOpsResultTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/ConfigCompileDoesNotReadCurrentOrArtifactsTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/ConfigHashRendersGenerationIdTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CacheVerifyRendersFourGenerationArtifactsTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CacheVerifyDirtyReturnsExitCodeTwoTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CacheVerifyInvalidReturnsExitCodeThreeTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CommandInputValidatorRejectsUnknownOptionBeforeResolutionTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CommandInputValidatorPreservesRepeatableOptionOrderTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/GlobalFormatOptionIsNotPassedToPackageCommandTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/DoctorBypassesKernelOperationsHostTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/NormalCommandsBootKernelOperationsHostTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/DoctorDoesNotLeakSecretsTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CliRejectsCliModeKeysInConfigDeterministicallyTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CliRejectsLegacyCommandRegistryDeterministicallyTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CoretsiaBinaryListCommandTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CoretsiaBinaryHelpCommandTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/ReservedCommandNamesCollisionRejectedTest.php`

  - [ ] `framework/packages/platform/cli/tests/Integration/CliHostResolvesCanonicalSensitiveDataRedactorTest.php`
    - [ ] the composed source host resolves exactly one `SensitiveDataRedactorInterface`
    - [ ] `OutputFormatter` receives the contracts port
    - [ ] no platform/cli class imports or instantiates `DefaultSensitiveDataRedactor`

  - [ ] `framework/packages/platform/cli/tests/Integration/KernelCommandsRejectUndeclaredModeAndPresetOptionsTest.php`
    - [ ] Kernel commands do not declare `mode` or `preset`
    - [ ] both options fail before command service resolution

  - [ ] `framework/packages/platform/cli/tests/Integration/CliUsesMergedValidatedConfigurationTest.php`
    - [ ] skeleton override changes effective CLI output policy
    - [ ] invalid CLI config fails in the existing ConfigKernel validation pipeline
    - [ ] CliServiceFactory does not re-run validation

  - [ ] `framework/packages/platform/worker/tests/Integration/WorkerProviderSourceDefinitionsParityTest.php`
    - [ ] remove `MODE` arguments and `mode` expected fields
    - [ ] preserve source/definition metadata parity for the final six-key schema

  - [ ] `framework/packages/platform/cli/tests/Integration/CliConfigChangesAffectOutputPolicyTest.php`
    - [ ] changed format default affects `FormatResolver`
    - [ ] changed adaptive mapping affects adaptive resolution
    - [ ] changed color default affects `ColorResolver`
    - [ ] changed table width affects `TableFormatter`

  - [ ] `framework/packages/platform/cli/tests/Integration/CliCommandUowAttributesAreOwnedByCommandRunnerTest.php`
    - [ ] UoW type is `cli`
    - [ ] canonical command name is passed as `operation`
    - [ ] effective format is passed as `output_format`
    - [ ] effective format is not added to ContextStore
    - [ ] platform/cli performs no direct context writes

  - [ ] `framework/packages/platform/cli/tests/Integration/CliRejectsInvalidCommandOverridesDeterministicallyTest.php`
    - [ ] ConfigValidator rejects a non-map `cli.commands.overrides`
    - [ ] CommandOverrides rejects invalid dynamic command-name keys
    - [ ] CommandOverrides rejects invalid override value types
    - [ ] CommandOverrides rejects unknown fields
    - [ ] CommandOverrides rejects unknown command names

  - [ ] `framework/packages/platform/cli/tests/Integration/ExternalPackageCommandWithOwnerServiceDispatchTest.php`
    - [ ] command is contributed by an enabled non-CLI package
    - [ ] command resolves an owner-package service
    - [ ] platform/cli imports no owner-package class
    - [ ] command is discovered, validated, resolved, and dispatched through generic infrastructure

  - [ ] `framework/packages/platform/cli/tests/Integration/ExternalPackageCommandMayDeclareDomainModeOptionTest.php`
    - [ ] external command declares `mode` in its own `OPTIONS`
    - [ ] descriptor validation accepts it
    - [ ] value reaches the external command unchanged
    - [ ] it does not affect Kernel preset selection

  - [ ] `framework/packages/platform/cli/tests/Integration/ExternalTaggedCommandIsLazyDiscoveredTest.php`
    - [ ] external tagged command appears in catalog
    - [ ] command constructor is not called during catalog/list/help descriptor build
    - [ ] command constructor is called only on dispatch

  - [ ] `framework/packages/platform/cli/tests/Integration/WorkerCommandMetadataCompatibilityTest.php`
    - [ ] when `platform.worker` is enabled, worker command tag metadata passes `CommandTagSchema`
    - [ ] worker command service ids are discovered through generic `cli.command`
    - [ ] `platform/cli` production source still does not import `Coretsia\Platform\Worker\*`
    - [ ] worker command metadata uses empty `ARGUMENTS` and `OPTIONS`
    - [ ] undeclared options are rejected before Worker command resolution
    - [ ] `--target` is rejected because Worker commands do not declare it
    - [ ] CLI-global `--format` is consumed before Worker command input is constructed
    - [ ] worker command metadata contains no `mode` key
    - [ ] worker command classes contain no `MODE` constant
    - [ ] CLI-global `--color` is consumed before Worker command input is constructed

  - [ ] `framework/packages/platform/cli/tests/Integration/ConfigCompileDelegatesToKernelOpsPortTest.php`
    - [ ] asserts exactly one `KernelOpsInterface` compile call
    - [ ] asserts exactly one explicit app target is passed
    - [ ] asserts the command does not resolve any Kernel compile-time service
    - [ ] asserts no Composer metadata reader is touched by CLI
    - [ ] asserts no artifact compiler is resolved by CLI

  - [ ] `framework/packages/platform/cli/tests/Integration/CacheVerifyDelegatesToKernelOpsPortTest.php`
    - [ ] asserts exactly one `KernelOpsInterface` verify call
    - [ ] asserts exactly one explicit app target is passed
    - [ ] asserts the command does not resolve any Kernel compile-time service
    - [ ] asserts no Composer metadata reader is touched by CLI
    - [ ] asserts no cache verifier is resolved by CLI

  - [ ] `framework/packages/platform/cli/tests/Integration/DebugModulesDelegatesToKernelOpsPortTest.php`
    - [ ] exactly one `debugModules()` call
    - [ ] no module-resolution service is resolved by CLI

  - [ ] `framework/packages/platform/cli/tests/Integration/ConfigValidateDelegatesToKernelOpsPortTest.php`
    - [ ] exactly one `validateConfig()` call
    - [ ] no ConfigKernel or module-resolution service is resolved by CLI

  - [ ] `framework/packages/platform/cli/tests/Integration/ConfigDebugDelegatesToKernelOpsPortTest.php`
    - [ ] exactly one `debugConfig()` call
    - [ ] only safe `OpsResult` data reaches output

  - [ ] `framework/packages/platform/cli/tests/Integration/ConfigHashDelegatesToKernelOpsPortTest.php`
    - [ ] exactly one `hashConfig()` call
    - [ ] no fingerprint or module-resolution service is resolved by CLI

  - [ ] `framework/packages/platform/cli/tests/Integration/ExternalTaggedCommandDiscoveryTest.php`
    - [ ] proves commands from an enabled non-CLI package are discovered through `cli.command`
    - [ ] MUST NOT rely on filesystem scanning
    - [ ] MUST NOT use a `cli.commands` registry list

  - [ ] `framework/packages/platform/cli/tests/Integration/WorkerCommandsAreDiscoverableWhenWorkerPackageEnabledTest.php`
    - [ ] enables `platform.worker` in a composed test fixture/app
    - [ ] asserts `worker:start`, `worker:stop`, and `worker:status` appear in the command catalog
    - [ ] asserts discovery happens via `cli.command`
    - [ ] MUST NOT require `platform/cli` compile-time dependency on `platform/worker`

  - [ ] `framework/packages/platform/cli/tests/Integration/WorkerStartDispatchesThroughCommandCatalogTest.php`
    - [ ] dispatches `worker:start` through `CliApplication` / `CommandCatalog`
    - [ ] uses a safe fake worker manager or fake command handler
    - [ ] MUST NOT start real worker processes
    - [ ] MUST NOT fork, call `proc_open`, or open sockets

### DoD (MUST)

- [ ] normal commands are discovered only through `cli.command`
- [ ] Kernel operation commands require explicit `--target`
- [ ] built-in Kernel operation commands reject undeclared `--mode` and `--preset`
- [ ] effective preset comes only from Kernel `OpsResult`
- [ ] each Kernel command makes exactly one `KernelOpsInterface` call
- [ ] platform/cli infrastructure and built-in Kernel commands never read Kernel artifacts or `current`
- [ ] output is deterministic and redacted
- [ ] external package commands remain lazy and package-agnostic
- [ ] a new enabled package can contribute and dispatch a command without any platform/cli production-source change
- [ ] external commands may declare owner-specific arguments and options without entering KernelOpsInterface
- [ ] `doctor` is the only pre-host command path
- [ ] config defaults and rules are exact and synchronized
- [ ] only CliServiceFactory reads `cli.*` configuration
- [ ] normal command execution uses exactly one Kernel UoW
- [ ] platform/cli performs no direct ContextStore writes or reset orchestration
- [ ] command observability is emitted through injected ports
- [ ] observability failures do not affect command exit semantics
- [ ] redaction cannot be disabled
- [ ] `OutputFormatter` consumes only `SensitiveDataRedactorInterface`.
- [ ] `platform/cli` defines no package-local redaction engine, policy, classifier, hasher, or pattern registry.
- [ ] `framework/packages/platform/cli/src/Redaction/` is absent.
- [ ] `framework/packages/platform/cli/src/Output/Redaction/` is absent.
- [ ] adaptive format depends only on explicit terminal capabilities
- [ ] color policy is `auto|always|never`
- [ ] JSON output is always ANSI-free
- [ ] configurable ANSI palettes are not introduced
- [ ] every legacy Phase-0 production file listed under `Deletes` is absent
- [ ] every retained legacy path listed as a complete rewrite contains no Phase-0 behavior
- [ ] no compatibility fallback reads `cli.commands` as an FQCN list
- [ ] no command is instantiated through reflection or `new $fqcn`
- [ ] Worker command metadata matches the final six-key schema
- [ ] after autoload, `ConsoleOutputWriter` is the only production output sink; the fixed pre-autoload failure is the sole exception
- [ ] effective format is a UoW attribute, not a ContextStore key
- [ ] all new metric and span names are registered in observability SSoT
- [ ] every Throwable after successful `CliApplication` resolution is handled by `CliApplication`
- [ ] pre-application host failures are handled by `CliHostBootstrap`
- [ ] pre-autoload failures are handled by the fixed binary fallback
- [ ] `CommandRunner` owns execution lifecycle but not error rendering
- [ ] formatter/redactor failure uses one non-recursive fixed fallback
- [ ] command output records and final stdout/stderr bytes have explicit immutable shapes
- [ ] built-in command metadata is complete and exact
- [ ] generic external-package command tests use explicit fixture files
- [ ] Worker compatibility tests use the composed Worker package fixture/application
- [ ] Worker production code and Worker tests use the same six-key command metadata schema
- [ ] built-in Kernel operation commands invoke `KernelOpsInterface` directly without a CLI-owned forwarding façade
- [ ] `KernelOpsRequestResolver` is a source-host-only input adapter and has no `CommandDescriptor` dependency
- [ ] `CommandOutputBuffer` is invocation-local and absent from container definitions
- [ ] `platform/cli` does not bind the global `ErrorHandlerInterface` service id
- [ ] redacted output maps are canonically re-sorted before formatting
- [ ] the source host resolves exactly one `SensitiveDataRedactorInterface` implementation
- [ ] command failures clear and reuse the same invocation-local `CommandOutputBuffer`
- [ ] every `cli.command` service id is the exact command class FQCN

---

### 2.40.0 Platform CLI — Deterministic Workflows + Smart Suggestions (SHOULD) [IMPL]

---
type: package
phase: 2
epic_id: "2.40.0"
owner_path: "framework/packages/platform/cli/"

package_id: "platform/cli"
composer: "coretsia/platform-cli"
kind: runtime
module_id: "platform.cli"

goal: "Надати `coretsia workflow:run <workflow>` як детермінований config-defined composite execution поверх tag-first CommandCatalog, з окремим canonical CLI UoW для кожного step, одним фінальним redacted render і стабільними suggestions для невідомих command names."
provides:
- "Config-defined workflows without filesystem discovery, templates, macros, environment interpolation, or reflective command construction"
- "Every workflow step resolved through the final tag-first CommandCatalog"
- "Every workflow step executed through the existing CommandRunner with exactly one Kernel CLI UoW"
- "One platform-owned composite-command boundary for `workflow:run`, without a nested outer CLI UoW"
- "Fail-fast sequential workflow execution with ordered step results"
- "Deterministic unknown-command suggestions derived only from the final CommandCatalog"
- "One final output formatting and mandatory defense-in-depth redaction pass"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-XXXX-cli-composite-workflows.md"
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/observability.md"
- "docs/ssot/sensitive-data-redaction.md"
- "docs/ssot/context-keys.md"
- "docs/ssot/context-store.md"
- "docs/ssot/stateful-services.md"
---

### Scope correction and replacement boundary (MUST)

This epic replaces the earlier incomplete 2.40.0 draft.

The implementation scope is single-choice:

- deterministic config-defined workflows;
- deterministic command-name suggestions;
- no CLI replay persistence capability.

This epic MUST NOT introduce:

- `cli_replay@1` or `cli-replay@1`;
- `docs/ssot/cli-replay.md`;
- replay storage, recording, playback, retention, listing, or cleanup services;
- a replay path config key;
- a package-local redaction engine;
- command argument or option recording;
- raw argv persistence;
- output transcript persistence.
- an interactive prompt service;
- a confirmation service;
- a preview renderer;
- a workflow-specific diagnostics renderer;
- a workflow-specific redaction service;
- a suggestion-specific redaction service;
- a second output or formatting pipeline.

Replay is excluded because the previous text did not define one coherent capability boundary for executable replay versus output playback, redaction handoff, persistence trigger, storage identity, atomic publication, read validation, retention, or consumer semantics.

A future replay capability MUST be specified in a separate epic before any artifact identity or persistence API is introduced.

The previous example:

```text
coretsia workflow:run verify --mode=enterprise
```

is replaced by:

```text
coretsia workflow:run verify
```

`workflow:run` MUST NOT declare `--mode` or `--preset`.

A workflow definition contains the exact command arguments and command-owned options for every step. Kernel operation steps therefore carry their own explicit `target` option inside the workflow definition.

Workflow configuration MUST NOT modify Kernel Bootstrap preset selection.

### Dependencies (MUST)

#### Epic prerequisites (MUST)

- 2.25.0 — Kernel Ops façade and source-operations host exist.
- 2.27.0 — `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` exists and one implementation is available in the source host.
- 2.30.0 — tag-first CLI baseline exists, including:
  - `CommandCatalog`;
  - `CommandDescriptor`;
  - `CommandTagSchema`;
  - `ArgvInputParser` and normalized `InputInterface` implementation;
  - `CommandInputValidator`;
  - `CommandRunner`;
  - invocation-local `CommandOutputBuffer`;
  - immutable `CommandOutputBatch`;
  - `OutputFormatter`;
  - `CliApplication`;
  - exact six-key `cli.command` metadata;
  - one canonical Kernel CLI UoW for every normal command;
  - mandatory final output redaction.

#### Required contracts and runtime APIs (MUST)

- `Coretsia\Contracts\Cli\Command\CommandInterface`
- `Coretsia\Contracts\Cli\Input\InputInterface`
- `Coretsia\Contracts\Cli\Output\OutputInterface`
- `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
- `Coretsia\Foundation\Tag\ReservedTags`
- `Coretsia\Kernel\Runtime\UnitOfWorkType`
- `Psr\Container\ContainerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- command-owner packages solely for workflow discovery or execution;
- `integrations/*` solely for workflow discovery or execution;
- external console frameworks or workflow engines;
- filesystem scanning for workflow definitions;
- filesystem scanning for commands;
- reflection-based command construction;
- `new $fqcn` command construction;
- direct Kernel artifact, module, provider-plan, config-compile, fingerprint, or cache-verification orchestration;
- direct `ContextStore` or reset orchestration access;
- package-local sensitive-data redaction implementation.

### Cross-package modification boundary (MUST)

The only files outside `framework/packages/platform/cli/` that this epic may create or modify are:

- `docs/adr/ADR-XXXX-cli-composite-workflows.md`
- `docs/adr/INDEX.md`
- `docs/ssot/tags.md`
- `docs/ssot/observability.md`

No command-owner package may be modified solely to make its commands workflow-compatible.

### Workflow ownership and execution model (MUST)

`platform/cli` owns only workflow orchestration.

Command owners continue to own:

- command identity;
- arguments and options;
- structural metadata;
- domain validation;
- dependencies;
- command output;
- returned process exit code.

A workflow is an ordered list of existing command invocations.

A workflow MUST NOT:

- define PHP callbacks;
- define service ids;
- define command class names;
- define providers;
- define aliases;
- define environment-variable substitutions;
- define shell fragments;
- define working directories;
- define path scanning;
- define file globs;
- define templates or macros;
- define conditional expressions;
- define loops;
- define parallel branches;
- define retries;
- define continue-on-error policy;
- invoke another composite command.

Workflow execution is sequential and fail-fast.

Canonical flow:

```text
workflow:run <workflow>
→ final WorkflowCatalog
→ ordered WorkflowDefinition
→ for each WorkflowStep
   → final CommandCatalog
   → normal CommandDescriptor
   → structured step input construction
   → existing CommandInputValidator
   → fresh child CommandOutputBuffer
   → existing CommandRunner
   → exactly one Kernel CLI UoW
   → lazy command service resolution
   → owner CommandInterface::run(...)
   → child CommandOutputBatch
→ ordered WorkflowResult
→ one top-level OutputInterface JSON-like record
→ one final OutputFormatter redaction + formatting pass
→ one ConsoleOutputWriter write
```

The first non-zero valid step exit code stops the workflow.

The workflow command returns that exact first non-zero exit code.

If all steps return `0`, the workflow command returns `0`.

A Throwable from a step MUST propagate unchanged through `WorkflowRunner` and enter the existing `CliApplication` error boundary.

`WorkflowRunner` MUST NOT catch, wrap, downgrade, or convert unexpected command Throwables into a completed workflow result.

### Composite command boundary (MUST)

2.30.0 defines one Kernel CLI UoW around every normal command and forbids nested CLI UoWs.

This epic narrows the earlier 2.30.0 exception wording.

After this epic:

- `doctor` remains the only pre-autoload/pre-host command path;
- `workflow:run` is the only source-host command allowed to execute without one outer Kernel CLI UoW;
- `workflow:list` remains a normal command and receives exactly one CLI UoW;
- every other normal tagged command receives exactly one CLI UoW;
- every command executed as a workflow step receives exactly one CLI UoW;
- no workflow step may be composite.

`workflow:run` is a platform-owned composite orchestration command and therefore MUST NOT receive an outer Kernel CLI UoW around the complete workflow.

Every actual workflow step still executes through the existing normal `CommandRunner` path and receives exactly one Kernel CLI UoW.

This epic introduces one internal platform-only execution contract:

```text
Coretsia\Platform\Cli\Runner\CompositeCommandInterface
```

Rules:

- it is internal to `platform/cli`;
- it is not a cross-package extension point;
- external command-owner packages MUST NOT import or implement it;
- it is not added to `core/contracts`;
- only the exact built-in `WorkflowRunCommand` service id may use it in this epic;
- its execution method receives:
  - validated `InputInterface`;
  - invocation-local `OutputInterface`;
  - effective output format `json|table|plain`;
- it returns one portable process exit code in `0..255`;
- it MUST NOT start a Kernel UoW directly;
- it MUST NOT write stdout or stderr;
- it MUST NOT render final output.

`CommandTagSchema` continues to require `CommandInterface` for every external tagged command.

The only exception is an immutable owner-maintained allowlist containing the exact `WorkflowRunCommand` service id, which must implement `CompositeCommandInterface`.

Composite capability MUST NOT be declared in `cli.command` metadata.

Unknown metadata keys remain forbidden.

`CommandDescriptor` gains one internal derived execution kind:

```text
normal
composite
```

The execution kind:

- is derived from the validated service-id class;
- is not tag metadata;
- is not configurable;
- is not overrideable;
- is not exported to external packages;
- is not included in command output.

`CommandRunner` remains stateless and becomes re-entrant for sequential workflow-step calls.

For a normal descriptor, existing 2.30.0 behavior is unchanged.

For the exact composite descriptor:

- command resolution remains lazy;
- service/interface validation remains mandatory;
- descriptor-name verification remains mandatory;
- exit-code validation remains mandatory;
- execution occurs without an outer Kernel UoW;
- one top-level `cli.command` observability event is emitted for `workflow:run`;
- every step emits its own existing normal `cli.command` observability through `CommandRunner`;
- no nested UoW is created.

`WorkflowRunner` MUST reject every step whose descriptor execution kind is `composite` before that step is executed.

### Workflow configuration (MUST)

#### Canonical config shape (MUST)

`framework/packages/platform/cli/config/cli.php` adds exactly:

```php
'workflows' => [
    'definitions' => [],
],
```

Canonical dot key:

```text
cli.workflows.definitions
```

No workflow feature flag is introduced.

An empty definitions map means no configured workflows.

The following keys MUST NOT be introduced:

```text
cli.workflows.enabled
cli.workflows.paths
cli.workflows.directories
cli.workflows.scan
cli.workflows.templates
cli.workflows.macros
cli.workflows.retries
cli.workflows.parallel
cli.workflows.continue_on_error
cli.ux.replay.*
cli.replay.*
```

#### Config rules (MUST)

`framework/packages/platform/cli/config/rules.php` is modified so:

- `cli` remains a required map with `additionalKeys = false`;
- `cli.workflows` is a required map with `additionalKeys = false`;
- `cli.workflows` contains exactly `definitions`;
- `cli.workflows.definitions` is a required map;
- `cli.workflows.definitions` permits dynamic map keys structurally with `additionalKeys = true`;
- ConfigValidator validates only the map boundary at the dynamic definitions node;
- deep dynamic workflow validation belongs only to `WorkflowSchema`;
- no second ConfigKernel validator or callback-based rules are introduced.

#### Canonical workflow definition shape (MUST)

Example:

```php
'workflows' => [
    'definitions' => [
        'verify' => [
            'summary' => 'Validate and verify the web target.',
            'steps' => [
                [
                    'command' => 'config:validate',
                    'arguments' => [],
                    'options' => [
                        'target' => 'web',
                    ],
                ],
                [
                    'command' => 'cache:verify',
                    'arguments' => [],
                    'options' => [
                        'target' => 'web',
                    ],
                ],
            ],
        ],
    ],
],
```

Every workflow map value has exactly:

```text
summary
steps
```

`summary`:

- MUST be a non-empty trimmed single-line string;
- MUST contain no NUL, CR, LF, ESC, or unsafe control bytes;
- MUST be at most 256 bytes.

`steps`:

- MUST be a non-empty list;
- MUST contain at most 64 entries;
- MUST preserve declared order.

Every step has exactly:

```text
command
arguments
options
```

Workflow names MUST match:

```regex
\A[a-z][a-z0-9-]*\z
```

Command names MUST match `CommandInterface::COMMAND_NAME_PATTERN`.

Argument lists:

- MUST be lists;
- contain strings only;
- preserve declared order;
- contain no NUL, CR, LF, or unsafe control bytes.

Option maps:

- MUST be string-keyed maps;
- option names match `\A[a-z][a-z0-9-]*\z`;
- values are exactly `string|true|list<string>`;
- `true` represents one bare `--name` flag;
- `string` represents one `--name=value` option;
- `list<string>` represents repeated `--name=value` options in declared list order;
- `false` and `null` are forbidden in workflow definitions;
- map keys are normalized in byte-order `strcmp` order;
- repeatable option lists preserve declared value order;
- `format` and `color` are forbidden because they remain CLI-global options;
- values contain no NUL, CR, LF, or unsafe control bytes.

Bounds are fixed and non-configurable:

- maximum workflow-name length: 64 bytes;
- maximum summary length: 256 bytes;
- maximum workflows: 128;
- maximum steps per workflow: 64;
- maximum arguments per step: 32;
- maximum options per step: 32;
- maximum scalar argument or option string: 512 bytes;
- maximum repeatable values per option: 32.

Unknown keys hard-fail deterministically.

Floats, objects, resources, closures, and Throwables are forbidden.

Workflow names are exposed in `workflow:list` using byte-order `strcmp` order.

Workflow step order is preserved exactly as declared.

### Deliverables (MUST)

#### Creates

Workflow model:
- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowDefinition.php`
  - [ ] immutable readonly value
  - [ ] contains canonical name, summary, and ordered non-empty step list
  - [ ] no config repository, services, closures, or runtime state

- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowStep.php`
  - [ ] immutable readonly value
  - [ ] contains command name, ordered arguments, and normalized option map
  - [ ] no command object, descriptor, service id, or container

- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowSchema.php`
  - [ ] stateless deep validator and normalizer for dynamic workflow definitions
  - [ ] enforces the exact shapes and bounds in this epic
  - [ ] performs no command discovery or service resolution
  - [ ] throws only `WorkflowDefinitionInvalidException`
  - [ ] public diagnostics contain only stable reason tokens

- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowCatalog.php`
  - [ ] immutable finalized workflow catalog
  - [ ] consumes normalized definitions from `WorkflowSchema`
  - [ ] receives the final `CommandCatalog`
  - [ ] validates every configured step command exists
  - [ ] validates every step command is `normal`, not `composite`
  - [ ] an unknown configured step command throws `WorkflowDefinitionInvalidException`
    - [ ] reason `workflow-command-unknown`
  - [ ] a configured composite step throws `WorkflowDefinitionInvalidException`
    - [ ] reason `workflow-command-composite`
  - [ ] these failures expose no arguments, option values, service ids, paths, or workflow definition payload
  - [ ] performs no command service resolution
  - [ ] sorts workflow names by byte-order `strcmp`
  - [ ] preserves step order
  - [ ] canonical APIs:
    - [ ] `public function definitions(): array`
    - [ ] `public function require(string $name): WorkflowDefinition`
  - [ ] `require()` validates the requested lookup token before map access:
    - [ ] same workflow-name regex
    - [ ] maximum 64 bytes
    - [ ] no control bytes
  - [ ] an invalid lookup token throws `WorkflowNotFoundException` without exposing the token
  - [ ] an unknown but valid lookup token MAY be exposed by `WorkflowNotFoundException`

- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowStepInputFactory.php`
  - [ ] stateless structured-input adapter
  - [ ] creates the existing normalized CLI `InputInterface` implementation from one `WorkflowStep`
  - [ ] MUST NOT use shell parsing or shell escaping
  - [ ] MUST NOT read raw argv, config, env, CWD, or filesystem
  - [ ] constructs the normalized `InputInterface` directly; it MUST NOT reparse the generated tokens
  - [ ] constructs canonical command-facing `tokens()` only as the stable token projection of the structured step:
    - [ ] command name first
    - [ ] option keys in byte-order `strcmp` order
    - [ ] `true` as `--name`
    - [ ] `string` as `--name=value`
    - [ ] `list<string>` as repeated `--name=value` tokens preserving list order
    - [ ] when positional arguments exist, one `--` marker before the first argument
    - [ ] positional arguments in declared order
  - [ ] CLI-global `format|color` tokens are never generated
  - [ ] the existing `CommandInputValidator` validates normalized arguments and options, not by reparsing `tokens()`
  - [ ] performs only deterministic structured-input construction
  - [ ] MUST NOT invoke `CommandInputValidator`
  - [ ] MUST NOT perform descriptor, argument, option, required-value, or repeatability validation
  - [ ] MUST NOT implement a second argument/option validation policy

- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowStepResult.php`
  - [ ] immutable readonly value
  - [ ] contains exactly:
    - [ ] `index: int`
    - [ ] `command: string`
    - [ ] `exitCode: int`
    - [ ] `records: list<normalized output record>`
  - [ ] contains no arguments, options, raw tokens, command object, service id, or paths added by workflow infrastructure

- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowResult.php`
  - [ ] immutable readonly value
  - [ ] contains exactly:
    - [ ] workflow name
    - [ ] `success|failure` outcome
    - [ ] final exit code
    - [ ] ordered executed-step results
  - [ ] contains no unexecuted synthetic steps
  - [ ] exports one recursively normalized json-like map

- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowRunner.php`
  - [ ] stateless and re-entrant
  - [ ] receives:
    - [ ] final `CommandCatalog`
    - [ ] existing `CommandInputValidator`
    - [ ] existing `CommandRunner`
    - [ ] `WorkflowStepInputFactory`
  - [ ] for every step:
    - [ ] obtains the normal descriptor from the final `CommandCatalog`
    - [ ] constructs structured input through `WorkflowStepInputFactory`
    - [ ] invokes the existing `CommandInputValidator` exactly once
    - [ ] invokes `CommandRunner` only after successful validation
  - [ ] creates one fresh local `CommandOutputBuffer` per step
  - [ ] MUST NOT resolve command services directly
  - [ ] MUST NOT invoke `CommandInterface::run()` directly
  - [ ] MUST NOT invoke `KernelRuntimeInterface` directly
  - [ ] MUST NOT invoke hooks or reset orchestration
  - [ ] passes the same effective top-level output format to every step `CommandRunner` call
  - [ ] finalizes every successfully completed child output buffer exactly once
  - [ ] never finalizes the child buffer of a throwing step
  - [ ] preserves child record order
  - [ ] stops on the first non-zero returned exit code
  - [ ] MUST NOT catch Throwables for translation, wrapping, observability, or downgrade
  - [ ] MAY catch a step Throwable only to discard the current invocation-local child buffer
  - [ ] after discard, MUST rethrow the exact same Throwable instance
  - [ ] if a step throws, no completed `WorkflowResult` is produced
  - [ ] records from earlier completed steps are not rendered as partial workflow output
  - [ ] no child output from the failed workflow invocation reaches the final formatter
  - [ ] if a step throws:
    - [ ] the current child buffer is not appended to a completed `WorkflowResult`
    - [ ] the current child buffer is discarded before propagation
    - [ ] no partial child batch is formatted or written
    - [ ] no later step is resolved
    - [ ] the original Throwable propagates unchanged

Composite execution:
- [ ] `framework/packages/platform/cli/src/Runner/CompositeCommandInterface.php`
  - [ ] internal platform-only interface
  - [ ] canonical API:
    - [ ] `public function name(): string`
    - [ ] `public function run(InputInterface $input, OutputInterface $output, string $outputFormat): int`
  - [ ] `name()` MUST return the exact command `NAME` constant
  - [ ] `run()` receives:
    - [ ] validated `InputInterface`
    - [ ] invocation-local `OutputInterface`
    - [ ] effective format `json|table|plain`
  - [ ] `run()` returns one portable process exit code in `0..255`
  - [ ] the interface MUST NOT extend `CommandInterface`
  - [ ] one service MUST NOT implement both `CommandInterface` and `CompositeCommandInterface`
  - [ ] no external package extension semantics

Commands:
- [ ] `framework/packages/platform/cli/src/Command/WorkflowListCommand.php`
  - [ ] normal `CommandInterface` command
  - [ ] receives only `WorkflowCatalog`
  - [ ] renders safe workflow names and summaries
  - [ ] does not expose step arguments or option values
  - [ ] metadata:
    - [ ] `NAME = 'workflow:list'`
    - [ ] `SUMMARY = 'List configured CLI workflows.'`
    - [ ] `GROUP = 'workflow'`
    - [ ] `HIDDEN = false`
    - [ ] `ARGUMENTS = []`
    - [ ] `OPTIONS = []`

- [ ] `framework/packages/platform/cli/src/Command/WorkflowRunCommand.php`
  - [ ] implements internal `CompositeCommandInterface`
  - [ ] does not implement `Coretsia\Contracts\Cli\Command\CommandInterface`
  - [ ] `name()` returns `self::NAME`
  - [ ] implements the exact `CompositeCommandInterface::run(...)` signature
  - [ ] exposes the exact six public command metadata constants:
    - [ ] `NAME`
    - [ ] `SUMMARY`
    - [ ] `GROUP`
    - [ ] `HIDDEN`
    - [ ] `ARGUMENTS`
    - [ ] `OPTIONS`
  - [ ] receives only `WorkflowCatalog` and `WorkflowRunner`
  - [ ] requires one workflow argument
  - [ ] writes exactly one json-like `WorkflowResult` record on completed execution
  - [ ] returns the exact workflow final exit code
  - [ ] metadata:
    - [ ] `NAME = 'workflow:run'`
    - [ ] `SUMMARY = 'Run one configured CLI workflow.'`
    - [ ] `GROUP = 'workflow'`
    - [ ] `HIDDEN = false`
    - [ ] `ARGUMENTS` contains exactly:
      - [ ] `name = workflow`
      - [ ] `summary = Workflow name.`
      - [ ] `required = true`
      - [ ] `variadic = false`
    - [ ] `OPTIONS = []`

Suggestions:
- [ ] `framework/packages/platform/cli/src/UX/SmartSuggestor.php`
  - [ ] stateless
  - [ ] consumes only final visible `CommandDescriptor` names from `CommandCatalog`
  - [ ] excludes hidden commands
  - [ ] performs byte-wise lowercase ASCII comparison
  - [ ] uses `levenshtein()` only on already validated ASCII command names
  - [ ] maximum accepted distance:
    - [ ] command length `1..8` → `2`
    - [ ] command length `9+` → `3`
  - [ ] sorts candidates by:
    - [ ] distance ascending
    - [ ] final CommandCatalog order ascending for ties
  - [ ] returns at most three command names
  - [ ] performs no option, argument, workflow, filesystem, or package suggestions
  - [ ] contains no mutable cache

Errors:
- [ ] `framework/packages/platform/cli/src/Exception/WorkflowDefinitionInvalidException.php`
  - [ ] code `CORETSIA_CLI_WORKFLOW_DEFINITION_INVALID`
  - [ ] code-first deterministic public message
  - [ ] exposes only stable reason tokens
  - [ ] contains no definition values, arguments, options, paths, or previous Throwable message

- [ ] `framework/packages/platform/cli/src/Exception/WorkflowNotFoundException.php`
  - [ ] code `CORETSIA_CLI_WORKFLOW_NOT_FOUND`
  - [ ] fixed safe reason `workflow-not-found`
  - [ ] MAY expose only the already validated workflow-name token
  - [ ] contains no configured definition or step data

Docs:
- [ ] `docs/adr/ADR-XXXX-cli-composite-workflows.md`
  - [ ] records the owner-only composite-command exception
  - [ ] records one UoW per workflow step and no outer workflow UoW
  - [ ] records fail-fast sequential execution
  - [ ] records no replay/artifact scope
  - [ ] records no nested workflows or composite steps

#### Modifies

- [ ] `framework/packages/platform/cli/src/Output/CommandOutputBuffer.php`
  - [ ] preserve the existing `finalize()` and `discard()` APIs
  - [ ] broaden the authorized `discard()` callers to exactly:
    - [ ] the existing `CliApplication` top-level error boundary
    - [ ] `WorkflowRunner` for one throwing invocation-local child step
  - [ ] no other production caller may invoke `discard()`
  - [ ] `discard()` remains forbidden after finalization

- [ ] `framework/packages/platform/cli/config/cli.php`
  - [ ] add only `cli.workflows.definitions = []`
  - [ ] preserve all 2.30.0 output and command override defaults unchanged

- [ ] `framework/packages/platform/cli/config/rules.php`
  - [ ] add exact static `workflows.definitions` boundary
  - [ ] preserve `additionalKeys = false` at every static schema-owned level
  - [ ] dynamic keys are allowed only under `cli.commands.overrides` and `cli.workflows.definitions`

- [ ] `framework/packages/platform/cli/src/Catalog/CommandDescriptor.php`
  - [ ] add internal derived `executionKind: normal|composite`
  - [ ] value is not exported or configurable

- [ ] `framework/packages/platform/cli/src/Catalog/CommandTagSchema.php`
  - [ ] preserve the exact six metadata keys
  - [ ] preserve external `CommandInterface` requirement
  - [ ] allow `CompositeCommandInterface` only for exact built-in allowlisted service ids
  - [ ] validates the allowlisted composite class constants against the same exact six-key metadata schema
  - [ ] the composite exception changes only the execution interface requirement, not metadata validation
  - [ ] reject external composite implementations deterministically

- [ ] `framework/packages/platform/cli/src/Catalog/CommandCatalog.php`
  - [ ] add `public function find(string $name): ?CommandDescriptor`
  - [ ] `find()` performs no service resolution
  - [ ] preserve final catalog order

- [ ] `framework/packages/platform/cli/src/Runner/CommandRunner.php`
  - [ ] preserve the normal command path exactly
  - [ ] add exact composite branch described in this epic
  - [ ] remain stateless and re-entrant
  - [ ] preserve output, exit-code, interface, name, and observability validation
  - [ ] MUST NOT create an outer UoW for `WorkflowRunCommand`

- [ ] `framework/packages/platform/cli/src/Output/CommandOutputBatch.php`
  - [ ] add a read-only ordered `records()` accessor
  - [ ] accessor returns the already normalized immutable record list
  - [ ] no mutable reference is exposed

- [ ] `framework/packages/platform/cli/src/Application/CliApplication.php`
  - [ ] unknown command selection uses `CommandCatalog::find()`
  - [ ] writes the canonical unknown-command error record
  - [ ] obtains suggestions only through `SmartSuggestor`
  - [ ] when suggestions exist, appends one json-like record:
    - [ ] `suggestions => list<string>`
  - [ ] suggestions pass through the same final formatter and redactor
  - [ ] no suggestion data enters logs, metrics, or UoW attributes

- [ ] `framework/packages/platform/cli/src/Diagnostics/CliErrorHandler.php`
  - [ ] add known safe mappings for:
    - [ ] `WorkflowDefinitionInvalidException`
    - [ ] `WorkflowNotFoundException`
  - [ ] preserve each exception’s stable public error code
  - [ ] use fixed safe public messages
  - [ ] MUST NOT include workflow definitions, step data, arguments, option values, service ids, paths, or previous Throwable messages
  - [ ] returned descriptor extensions remain empty

- [ ] `framework/packages/platform/cli/src/Provider/CliServiceFactory.php`
  - [ ] remains the only `cli.*` config consumer
  - [ ] reads the complete validated `cli` root through the existing helper
  - [ ] constructs normalized workflow definitions through `WorkflowSchema`
  - [ ] performs no command service resolution

- [ ] `framework/packages/platform/cli/src/Provider/CliServiceProvider.php`
  - [ ] registers/defines workflow services in the same source/definition split as 2.30.0
  - [ ] tags `WorkflowListCommand` and `WorkflowRunCommand` with exact six-key metadata
  - [ ] tag priority is actual `0`
  - [ ] references command constants only
  - [ ] does not conditionally register commands from config

- [ ] `framework/packages/platform/cli/README.md`
  - [ ] document exact workflow config shape
  - [ ] document fail-fast behavior
  - [ ] document one UoW per step
  - [ ] replace the earlier “doctor is the only pre-UoW command” wording with:
    - [ ] `doctor` is the only pre-host command
    - [ ] `workflow:run` is the only source-host composite command without an outer UoW
    - [ ] every normal command and every workflow step has exactly one UoW
  - [ ] document no nested workflows
  - [ ] document no `--mode|--preset` workflow semantics
  - [ ] document deterministic suggestions
  - [ ] explicitly state replay persistence is not provided

- [ ] `docs/ssot/tags.md`
  - [ ] preserve exact six-key metadata
  - [ ] document the single platform-owned composite service-id exception
  - [ ] keep composite capability out of tag metadata
  - [ ] external command services still require `CommandInterface`

- [ ] `docs/ssot/observability.md`
  - [ ] retain existing `cli.command` names and labels
  - [ ] document that `workflow:run` emits one top-level command observation without a Kernel UoW
  - [ ] each workflow step emits its own normal `cli.command` observation inside its own UoW
  - [ ] workflow name, step arguments, and option values are forbidden metric labels
  - [ ] step arguments and option values are forbidden span attributes
  - [ ] `CommandRunner` remains the sole owner of both normal and composite CLI command lifecycle observability
  - [ ] `WorkflowRunner` MUST NOT emit duplicate step or workflow lifecycle signals
  - [ ] `WorkflowRunCommand` MUST NOT emit lifecycle signals
  - [ ] one user workflow invocation emits:
    - [ ] one top-level `cli.command` observation for `workflow:run`
    - [ ] zero or more normal `cli.command` observations, one for each executed step
  - [ ] unexecuted steps emit no observability

- [ ] `docs/adr/INDEX.md`
  - [ ] register ADR-XXXX-cli-composite-workflows.md

### Output, redaction, context, and state (MUST)

- [ ] Workflow infrastructure MUST NOT write stdout or stderr.
- [ ] Final workflow output MUST pass through the existing mandatory `OutputFormatter` redaction boundary exactly once.
- [ ] The existing `OutputFormatter` remains the sole workflow and suggestion rendering consumer of `SensitiveDataRedactorInterface`.
- [ ] `WorkflowRunCommand`, `WorkflowRunner`, `WorkflowCatalog`, `WorkflowSchema`, `SmartSuggestor`, and workflow result DTOs MUST NOT:
  - [ ] receive `SensitiveDataRedactorInterface`
  - [ ] resolve a redactor
  - [ ] instantiate a concrete redactor
  - [ ] define a redaction classifier, policy, hasher, or pattern registry
- [ ] Workflow results, suggestions, and workflow error records MUST NOT bypass the existing 2.30.0 output/redaction pipeline.
- [ ] Child command batches MUST NOT be redacted separately.
- [ ] This epic introduces no prompts, confirmations, previews, or separate interactive diagnostic-output path.
- [ ] Child step batches MUST NOT be formatted separately.
- [ ] Child step batches MUST NOT be written directly.
- [ ] Workflow infrastructure MUST NOT persist output.
- [ ] Workflow infrastructure MUST NOT log arguments, options, child records, paths, payloads, or workflow definition values.
- [ ] All 2.30.0 context, UoW, reset, observability, and redaction rules remain normative except for the explicitly documented missing outer UoW around `workflow:run`.
- [ ] The `CommandRunner` composite branch owns the top-level `workflow:run` lifecycle observation.
- [ ] `WorkflowRunCommand` and `WorkflowRunner` MUST NOT receive:
  - [ ] `ContextAccessorInterface`
  - [ ] `ContextKeys`
  - [ ] `TracerPortInterface`
  - [ ] `MeterPortInterface`
  - [ ] `LoggerInterface`
  - [ ] `Stopwatch`
- [ ] `WorkflowRunCommand` and `WorkflowRunner` MUST NOT emit command lifecycle spans, metrics, or logs.
- [ ] Top-level `workflow:run` observability MUST NOT:
  - [ ] synthesize a correlation id
  - [ ] synthesize a UoW id
  - [ ] read correlation or UoW ids left by a completed step
  - [ ] aggregate step correlation or UoW ids
- [ ] Each normal step retains the existing 2.30.0 context policy inside its own Kernel UoW.
- [ ] Step correlation ids and UoW ids MUST NOT be included in `WorkflowStepResult` or `WorkflowResult`.
- [ ] Top-level workflow observability MAY use only:
  - [ ] operation `workflow:run`;
  - [ ] outcome;
  - [ ] final exit code as a bounded span/log attribute under existing policy.
  - [ ] Top-level `workflow:run` observability uses no context-derived fields.
- [ ] Workflow name MUST NOT be a metric label.
- [ ] Platform CLI MUST NOT write `ContextStore` directly.
- [ ] Every step UoW is created and completed only by the existing `CommandRunner` and `KernelRuntimeInterface` path.
- [ ] Reset occurs after each step through the canonical Kernel UoW lifecycle.
- [ ] No workflow object, result, child buffer, or current-step state is stored in a shared service.
- [ ] All workflow services are immutable/stateless except invocation-local child buffers.
- [ ] No workflow service implements `ResetInterface`.

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowRunnerUsesFreshChildBufferPerStepTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowRunnerPropagatesThrowableUnchangedTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowCatalogRejectsUnknownCommandTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowCatalogRejectsCompositeStepTest.php`

  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowStepInputFactoryCanonicalProjectionTest.php`
    - [ ] option keys use `strcmp` order
    - [ ] bare flags use `true`
    - [ ] repeated values preserve list order
    - [ ] `--` separates positional arguments
    - [ ] false and null config values are rejected by `WorkflowSchema`

  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowRunnerDiscardsThrowingChildBufferTest.php`
    - [ ] the throwing child buffer is discarded
    - [ ] the same Throwable instance is rethrown
    - [ ] no completed workflow result is emitted

  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowSchemaTest.php`
    - [ ] accepts the exact canonical shape
    - [ ] rejects unknown keys, macros, templates, floats, unsafe strings, invalid option values, and all bounds violations

  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowCatalogDeterminismTest.php`
    - [ ] workflow names are `strcmp` sorted
    - [ ] step order is preserved
    - [ ] no command service is instantiated

  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowRunnerStopsOnFirstNonZeroExitCodeTest.php`
    - [ ] exact first non-zero code is returned
    - [ ] later steps are not resolved

  - [ ] `framework/packages/platform/cli/tests/Unit/SmartSuggestorTest.php`
    - [ ] fixed thresholds
    - [ ] maximum three results
    - [ ] hidden commands excluded
    - [ ] catalog-order tie-break

  - [ ] `framework/packages/platform/cli/tests/Unit/CommandRunnerCompositeExecutionTest.php`
    - [ ] no outer Kernel UoW for `workflow:run`
    - [ ] normal commands remain unchanged

- Contract:
  - [ ] `framework/packages/platform/cli/tests/Contract/WorkflowConfigShapeContractTest.php`
  - [ ] `framework/packages/platform/cli/tests/Contract/WorkflowCommandsUseExactTagMetadataTest.php`
  - [ ] `framework/packages/platform/cli/tests/Contract/OnlyWorkflowRunMayUseCompositeCommandInterfaceTest.php`
  - [ ] `framework/packages/platform/cli/tests/Contract/WorkflowInfrastructureDoesNotWriteToStdoutTest.php`

  - [ ] `framework/packages/platform/cli/tests/Contract/WorkflowUxIntroducesNoRedactionBoundaryContractTest.php`
    - [ ] workflow and suggestion classes do not depend on a concrete redactor
    - [ ] workflow and suggestion classes do not receive `SensitiveDataRedactorInterface`
    - [ ] no workflow-local or suggestion-local classifier, policy, hasher, or pattern registry exists
    - [ ] no prompt, confirmation, preview, or separate diagnostics renderer is introduced
    - [ ] the existing `OutputFormatter` remains the sole final redaction boundary

  - [ ] `framework/packages/platform/cli/tests/Contract/WorkflowEpicIntroducesNoReplayArtifactTest.php`
    - [ ] no replay classes, config keys, artifact registry entry, or replay SSoT exists

- Integration:
  - [ ] `framework/packages/platform/cli/tests/Integration/CliErrorHandlerMapsWorkflowExceptionsSafelyTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowRunAggregatesOrderedStepRecordsTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowRunRejectsModeAndPresetOptionsTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowRunKernelOpsStepsUseDefinitionTargetTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/UnknownCommandSuggestionsUseFinalCatalogTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowDefinitionsRequireNoFilesystemScanningTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowRunExecutesEveryStepThroughCommandRunnerTest.php`

  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowRunFinalOutputIsRedactedOnceTest.php`
    - [ ] completed workflow output invokes the shared redaction port exactly once
    - [ ] child step batches are not redacted separately
    - [ ] raw sensitive fixture values from child records are absent from final output
    - [ ] no workflow-specific redactor is resolved
    - [ ] record order remains unchanged after redaction

  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowRunUsesOneKernelUowPerStepTest.php`
    - [ ] no outer workflow UoW
    - [ ] no nested UoW
    - [ ] reset completes after every step

### DoD (MUST)

- [ ] Workflows are loaded only from validated `cli.workflows.definitions`.
- [ ] No workflow filesystem discovery exists.
- [ ] No templates, macros, environment interpolation, shell commands, retries, branches, or parallel execution exist.
- [ ] Workflow names are deterministic and `strcmp` sorted.
- [ ] Step order is preserved.
- [ ] Every step command comes from the final tag-first `CommandCatalog`.
- [ ] No command service is resolved during workflow catalog construction.
- [ ] `workflow:run` has no outer Kernel UoW.
- [ ] Every workflow step has exactly one normal Kernel CLI UoW.
- [ ] No nested workflow/composite step is allowed.
- [ ] The first non-zero step code stops execution and is returned unchanged.
- [ ] Step Throwables propagate unchanged to the existing CLI error boundary.
- [ ] Workflow child output is aggregated in order and formatted/redacted once.
- [ ] Workflow and suggestion infrastructure introduces no second redaction or rendering model.
- [ ] No prompt, confirmation, preview, or separate interactive diagnostics pipeline exists in this epic.
- [ ] The shared redaction port is consumed only through the existing final `OutputFormatter`.
- [ ] Suggestions are deterministic, bounded, catalog-backed, and exclude hidden commands.
- [ ] No replay artifact, storage, path config, recorder, or replay SSoT is introduced.
- [ ] All unit, contract, integration, architecture, ECS, PHPStan, and package-compliance checks pass.

---

### 2.50.0 Target-aware skeleton HTTP front controllers + deterministic smoke (MUST) [IMPL]

---
type: skeleton
phase: 2
epic_id: "2.50.0"
owner_path: "skeleton/apps/"

goal: "Надати стабільні target-aware HTTP front controllers для skeleton web/api та deterministic `composer serve` / `composer smoke:http` flow до появи platform/http; front-controller paths залишаються незмінними, а тимчасовий 503 fallback пізніше замінюється реальним HTTP runtime bootstrap."
provides:
- "Real stable HTTP entrypoints: `skeleton/apps/web/public/index.php` and `skeleton/apps/api/public/index.php`."
- "Explicit canonical target ownership: each front controller hardcodes exactly one target, `web` or `api`."
- "Shared non-public HTTP bootstrap seam that currently emits deterministic 503 and is replaced internally when platform/http becomes available."
- "Target-aware dev server command using `--target=web|api`."
- "Pure-PHP socket-based HTTP smoke checker with byte-exact body verification and no cURL dependency."
- "Deterministic smoke orchestration: start one server, wait for readiness, execute one checker, and terminate the server."
- "No request reflection, superglobal capture, request headers, cookies, request bodies, paths, env values, or process diagnostics in public output."
- "Repo-root Composer entrypoints with CWD-independent script behavior."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/architecture/PACKAGING.md"
- "docs/ssot/modes.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables:
  - `skeleton/apps/web/public/` exists
  - `skeleton/apps/api/public/` exists
  - repo-root `composer.json` exists

- Existing packaging enforcement retained unchanged:
  - `framework/tools/gates/no_skeleton_http_default_gate.php`

- Gate boundary:
  - the gate forbids only repo-root `skeleton/config/http.php`
  - application front controllers under `skeleton/apps/<http-app>/public/` are not HTTP config defaults
  - this epic MUST NOT add an allowlist or exception to the gate

- New tooling boundary:
  - `framework/bin/serve` does not exist before this epic and is created here
  - `framework/bin/smoke` does not exist before this epic and is created here
  - `framework/bin/smoke-http` does not exist before this epic and is created here
  - source files imported from another project are implementation inputs only
  - imported implementations MUST be adapted completely to this epic and MUST NOT define compatibility requirements

- Canonical target tokens already exist:
  - `web`
  - `api`
  - `console`
  - `worker`

- Scope boundary:
  - this epic implements HTTP entrypoints only for `web|api`
  - `console` remains owned by platform/cli
  - `worker` remains owned by the worker runtime

### Entry points / integration points (MUST)

- Repo-root Composer scripts:
  - `composer serve`
    - delegates to `@php framework/bin/serve`
    - accepts script arguments after `--`
    - canonical target option: `--target=web|api`
  - `composer smoke:http`
    - delegates to `@php framework/bin/smoke http`
    - accepts script arguments after `--`
    - canonical target option: `--target=web|api`

- Target ownership:
  - `serve --target=web` selects `skeleton/apps/web/public`
  - `serve --target=api` selects `skeleton/apps/api/public`
  - `smoke http --target=<target>` starts and verifies the same target
  - target selection changes only the selected skeleton docroot
  - the public front controller independently declares the same canonical target
  - no command-line value is passed into the front controller as runtime target state

- Unsupported targets:
  - `console|worker` fail deterministically for `serve`
  - `console|worker` fail deterministically for `smoke http`
  - no HTTP entrypoint is created for those targets by this epic

### Deterministic CLI contract (MUST)

The following rules apply to `framework/bin/serve`, `framework/bin/smoke`, and `framework/bin/smoke-http`:

- long options use only `--name=value` form
- boolean flags use only `--name`
- split forms such as `--port 8080` are rejected
- unknown options are rejected
- duplicate options are rejected
- missing option values are rejected
- empty option values are rejected
- option names are ASCII case-sensitive
- positional arguments are forbidden except the exact `http` command accepted by `framework/bin/smoke`
- exact command layouts are:
  - `framework/bin/serve [options]`
  - `framework/bin/smoke http [options]`
  - `framework/bin/smoke-http [options]`
- for `framework/bin/smoke`, `http` must be the first argument after the script path
- options may appear in any order after the command token
- options before `http` are rejected
- additional positional arguments are rejected
- no option value is read from env or config

Host model:

- accepted `--host` values are exactly:
  - `localhost`
  - `127.0.0.1`
  - `0.0.0.0`
- every other hostname or IPv4 literal is rejected
- IPv6 host syntax is out of scope for this epic
- whitespace, control bytes, URI syntax, ports, paths, and user-info are forbidden inside `--host`

Canonical host derivation:

- requested `localhost`:
  - bind host = `127.0.0.1`
  - connect host = `127.0.0.1`
- requested `127.0.0.1`:
  - bind host = `127.0.0.1`
  - connect host = `127.0.0.1`
- requested `0.0.0.0`:
  - bind host = `0.0.0.0`
  - connect host = `127.0.0.1`

Usage:

- `serve` binds the PHP built-in server to the canonical bind host
- `serve` readiness probes use the canonical connect host
- `smoke` passes the canonical bind host to `serve`
- `smoke` readiness and cleanup probes use the canonical connect host
- `smoke` passes the canonical connect host to `smoke-http`
- `smoke-http` always connects through the canonical connect host
- `smoke-http` uses the canonical connect host in the HTTP `Host` field
- `localhost` is never passed to sockets or the PHP built-in server after canonicalization

Port validation:

- ASCII decimal digits only
- range `1..65535`
- no sign, whitespace, decimal point, exponent, or leading/trailing data

Timeout validation:

- ASCII decimal digits only
- range `1..60`
- default is exactly `5` seconds
- applies to `framework/bin/smoke` readiness and cleanup waits
- applies to `framework/bin/smoke-http` connect and response reads
- timeout measurement uses monotonic `hrtime(true)`
- wall-clock time, timezone, and system date do not participate
- each bounded phase receives one total deadline
- reading a partial chunk does not reset or extend a deadline
- `smoke` readiness polling interval is exactly `50` milliseconds
- `smoke` cleanup-port polling interval is exactly `50` milliseconds

Process result:

- successful finite commands exit with status `0`
- deterministic command failure exits with status `1`
- successful `serve` remains active until externally terminated
- every explicit command failure writes exactly one JSON line to stderr:
  - `{"schema":1,"code":"<CODE>"}\n`
- no command writes a PHP warning, stack trace, previous exception message, usage dump, or child-process output

Process execution and ephemeral IPC contract:

- platform null sink is:
  - `NUL` when `PHP_OS_FAMILY === 'Windows'`
  - `/dev/null` on every other supported OS
- every `proc_open` command uses an argument vector
- no child process inherits parent terminal streams implicitly
- stdout/stderr pipes are not used for readiness, control, or child diagnostics
- temporary IPC paths and contents never appear in stdout, stderr, HTTP output, or exceptions

`proc_open` options:

- on Windows:
  - `bypass_shell = true`
  - `suppress_errors = true`
- on every other supported OS:
  - no platform-specific process option is required
- on Windows, every `proc_open` call explicitly uses `bypass_shell = true`
- command arrays MUST NOT rely on implicit `cmd.exe` behavior

Bounded process termination:

- every termination deadline uses monotonic `hrtime(true)`
- process status is polled every `50` milliseconds
- graceful termination phase:
  - requests normal process termination
  - waits for at most the selected timeout
- hard termination phase runs only if the process remains active:
  - on POSIX, requests signal `9`
  - on Windows, repeats `proc_terminate` using native process termination
  - waits for at most one additional selected-timeout interval
- no process wait resets or extends its current deadline
- `proc_close` is called only after the process is observed inactive
- a process that remains active after the hard-termination deadline is a deterministic cleanup failure

`smoke` ephemeral control directory:

- before starting `serve`, `smoke` creates one private directory under `sys_get_temp_dir()`
- directory basename is exactly:
  - `coretsia-http-smoke-` followed by `bin2hex(random_bytes(16))`
- the generated suffix is internal collision-avoidance state only and does not participate in observable output
- directory creation uses exclusive semantics
- directory permissions are `0700` where the platform supports Unix permission modes
- an existing path, symlink, or failed directory creation maps to `CORETSIA_SMOKE_INTERNAL_FAILURE`
- the directory contains only:
  - `ready`
  - `ready.tmp`
  - `stop`
  - `stop.tmp`
- these files are ephemeral runtime IPC files, not generated artifacts
- `smoke` owns creation and final removal of the directory
- failure to remove owned IPC files or the directory after process startup maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
- failure to remove the owned control directory before any child process was successfully started maps to `CORETSIA_SMOKE_INTERNAL_FAILURE`
- control-directory cleanup is idempotent; repeated invocation performs no duplicate output and does not change an already selected failure unless cleanup itself fails

Atomic IPC file publication:

- `ready.tmp` and `stop.tmp` are created with exclusive-create semantics
- publication sequence is exact:
  1. confirm that both temporary and final destination paths are absent
  2. open the temporary path as a new regular file
  3. write all expected bytes using a complete-write loop
  4. flush the stream
  5. close the stream
  6. atomically rename the temporary file to its final filename in the same directory
- partial writes are never accepted
- rename never replaces an existing destination
- temporary and final paths must remain regular non-symlink paths
- readiness publication failure maps inside `serve` to `CORETSIA_SERVE_INTERNAL_FAILURE`
- stop publication failure inside `smoke` skips orderly stop and proceeds to bounded hard termination
- temporary IPC bytes are never read until publication rename has completed

Readiness IPC:

- `serve` receives the control directory through internal option `--control-dir=<absolute-path>`
- when no `--control-dir` is provided, direct interactive `serve` behavior remains unchanged
- when `--control-dir` is provided:
  - `--quiet` is required
  - the directory must already exist
  - the directory must not be a symlink
  - `ready`, `ready.tmp`, `stop`, and `stop.tmp` must initially be absent
- after built-in-server TCP readiness, `serve` publishes `ready` through the exact atomic IPC file-publication contract
- readiness bytes are exactly:
  - `CORETSIA_SERVE_READY target=<target> bind=<bind-host>:<port> url=http://<connect-host>:<port>\n`
- readiness is established only when:
  - `ready` exists
  - `ready` is a regular file
  - its complete contents equal the expected readiness bytes
  - the serve wrapper is still active
  - the post-readiness TCP probe succeeds
- malformed, oversized, unexpected, or symlinked readiness state maps to `CORETSIA_SMOKE_SERVER_START_FAILED`

Stop IPC:

- cleanup publishes `stop` with exact bytes `CORETSIA_SERVE_STOP\n` through the atomic IPC file-publication contract
- while active, `serve` polls for `stop` every `50` milliseconds
- `serve` accepts stop only when:
  - `stop` is a regular file
  - its contents equal `CORETSIA_SERVE_STOP\n` byte-for-byte
- valid stop state triggers orderly built-in-server termination and serve-wrapper exit status `0`
- malformed or symlinked stop state maps to `CORETSIA_SERVE_INTERNAL_FAILURE`
- EOF and stdin bytes have no serve-control semantics

Child descriptors:

- `serve` → PHP built-in server:
  - stdin = platform null sink
  - stdout = platform null sink
  - stderr = platform null sink
- `smoke` → `serve`:
  - stdin = platform null sink
  - stdout = platform null sink
  - stderr = platform null sink
- `smoke` → `smoke-http`:
  - stdin = platform null sink
  - stdout = platform null sink
  - stderr = platform null sink

Unexpected failure containment:

- each CLI script has one top-level failure boundary
- expected validation, process, socket, and response failures map to their documented codes
- every other caught `Throwable` maps to a script-local internal failure code:
  - `framework/bin/serve` → `CORETSIA_SERVE_INTERNAL_FAILURE`
  - `framework/bin/smoke` → `CORETSIA_SMOKE_INTERNAL_FAILURE`
  - `framework/bin/smoke-http` → `CORETSIA_SMOKE_HTTP_INTERNAL_FAILURE`
- previous Throwable objects are not retained after mapping
- Throwable messages, classes, traces, files, and line numbers are never copied into output
- PHP warnings produced by filesystem, process, stream, or socket operations are suppressed or converted inside the top-level failure boundary
- any temporary error handler is restored before command termination

### Skeleton HTTP packaging boundary (MUST)

- HTTP-facing skeleton apps MAY ship their canonical executable front controller:
  - `skeleton/apps/web/public/index.php`
  - `skeleton/apps/api/public/index.php`

- A front controller:
  - is an application entrypoint
  - is not a config root
  - MUST NOT own module selection
  - MUST NOT own runtime-driver selection
  - MUST NOT embed environment-specific HTTP configuration

- Default HTTP config remains forbidden:
  - `skeleton/config/http.php` MUST remain absent
  - the existing `no_skeleton_http_default_gate.php` remains unchanged
  - no path-specific allowlist is introduced

### Deliverables (MUST)

#### Creates

- [ ] Before implementation, re-review this epic against the current runtime-driver ownership boundary: `RuntimeDriverResolver` remains Kernel matrix-only; owner packages/adapters own their package/module prerequisites, adapter/transport/executable readiness, and `RuntimeDriverContributions` carry selected canonical drivers only.

- [ ] `skeleton/bootstrap/HttpFrontController.php`
  - [ ] declares final class `Coretsia\Skeleton\Bootstrap\HttpFrontController`
  - [ ] file is outside every public docroot
  - [ ] requiring the file only declares the class
  - [ ] requiring the file emits no output, headers, status, warnings, or side effects
  - [ ] exact typed public constant:
    - [ ] `public const string BOOT_NOT_READY_BODY`
    - [ ] exact value: `{"schema":1,"code":"CORETSIA_HTTP_BOOT_NOT_READY","message":"boot not ready"}\n`
  - [ ] public static entrypoint:
    - [ ] `public static function run(string $target): void`
  - [ ] accepts exactly:
    - [ ] `web`
    - [ ] `api`
  - [ ] any other target throws fixed `LogicException('http-front-controller-target-invalid')`
  - [ ] target validation occurs before any status, header, or output side effect
  - [ ] current Phase-2 behavior:
    - [ ] removes `X-Powered-By`
    - [ ] sets status `503`
    - [ ] sets `Content-Type: application/json; charset=utf-8`
    - [ ] sets `Cache-Control: no-store`
    - [ ] sets `Content-Length` from exact body bytes
    - [ ] outputs `BOOT_NOT_READY_BODY` exactly once
  - [ ] body is ASCII-only, LF-only, and ends with exactly one LF
  - [ ] performs no JSON encoding at request time
  - [ ] reads no superglobals
  - [ ] reads no config or env
  - [ ] requires no vendor autoloader or HTTP runtime
  - [ ] performs no request reflection
  - [ ] contains no timestamp, path, host, port, target, header, cookie, query, body, process, or exception data in output
  - [ ] is the stable bootstrap seam whose internal fallback is replaced by platform/http in Phase 3

- [ ] `skeleton/apps/web/public/index.php`
  - [ ] real stable web front controller
  - [ ] CWD-independent require of `skeleton/bootstrap/HttpFrontController.php`
  - [ ] calls exactly:
    - [ ] `HttpFrontController::run('web')`
  - [ ] contains no target inference
  - [ ] reads no superglobals
  - [ ] contains no fallback response implementation of its own
  - [ ] contains no executable code after the `run()` call

- [ ] `skeleton/apps/api/public/index.php`
  - [ ] real stable API front controller
  - [ ] CWD-independent require of `skeleton/bootstrap/HttpFrontController.php`
  - [ ] calls exactly:
    - [ ] `HttpFrontController::run('api')`
  - [ ] contains no target inference
  - [ ] reads no superglobals
  - [ ] contains no fallback response implementation of its own
  - [ ] contains no executable code after the `run()` call

- [ ] `framework/bin/serve`
  - [ ] new pure-PHP executable script
  - [ ] strict argument parser
  - [ ] CWD-independent repo-root discovery through `__DIR__`
  - [ ] accepts exactly:
    - [ ] `--target=<target>`
    - [ ] `--host=<host>`
    - [ ] `--port=<port>`
    - [ ] `--quiet`
    - [ ] `--control-dir=<absolute-path>` as an orchestration-only IPC option
  - [ ] defaults:
    - [ ] `target = web`
    - [ ] `host = 127.0.0.1`
    - [ ] `port = 8080`
    - [ ] `quiet = false`
    - [ ] `control-dir = null`
  - [ ] `--control-dir` rules:
    - [ ] path must be absolute
    - [ ] path must already exist as a directory
    - [ ] path must not be a symlink
    - [ ] path must contain none of the reserved IPC files before startup
    - [ ] option is valid only together with `--quiet`
    - [ ] invalid control-directory state maps to `CORETSIA_SERVE_INVALID_ARGUMENT`
    - [ ] rejected path is never included in failure output
  - [ ] MUST NOT accept:
    - [ ] `--app`
    - [ ] `--docroot`
    - [ ] `--router`
    - [ ] env overrides
    - [ ] arbitrary PHP binary
    - [ ] arbitrary child command
  - [ ] target mapping is exact:
    - [ ] `web` → `skeleton/apps/web/public`
    - [ ] `api` → `skeleton/apps/api/public`
  - [ ] target validation occurs after syntactic argument parsing:
    - [ ] `web|api` are accepted HTTP targets
    - [ ] `console|worker` fail with `CORETSIA_SERVE_TARGET_NOT_HTTP`
    - [ ] every other target fails with `CORETSIA_SERVE_TARGET_INVALID`
  - [ ] target comparison is exact ASCII and case-sensitive
  - [ ] router is always `<resolved-docroot>/index.php`
  - [ ] missing docroot fails with `CORETSIA_SERVE_DOCROOT_MISSING`
  - [ ] missing front controller fails with `CORETSIA_SERVE_FRONT_CONTROLLER_MISSING`
  - [ ] invalid arguments fail with `CORETSIA_SERVE_INVALID_ARGUMENT`
  - [ ] occupied port fails with `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] port availability is checked before `proc_open` through a temporary bind to the canonical bind host and selected port
  - [ ] the temporary socket is closed before the PHP built-in server is started
  - [ ] failure of the preflight bind maps only to `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] readiness polling connects through the canonical connect host
  - [ ] child startup failure fails with `CORETSIA_SERVE_START_FAILED`
  - [ ] unexpected post-readiness child exit fails with `CORETSIA_SERVE_CHILD_EXITED`
  - [ ] every other unexpected failure fails with `CORETSIA_SERVE_INTERNAL_FAILURE`
  - [ ] starts the PHP built-in server through `proc_open`
  - [ ] after `proc_open`, polls both:
    - [ ] child process status
    - [ ] TCP reachability of the canonical connect host and selected port
  - [ ] fixed startup timeout is exactly `5` seconds
  - [ ] readiness polling uses a fixed sleep interval of `50` milliseconds
  - [ ] child exit before TCP readiness maps to `CORETSIA_SERVE_START_FAILED`
  - [ ] startup timeout maps to `CORETSIA_SERVE_START_FAILED`
  - [ ] partial readiness diagnostics are never emitted
  - [ ] the readiness line is emitted only after a TCP connection succeeds
  - [ ] command is an argument vector using exactly:
    - [ ] `PHP_BINARY`
    - [ ] `-n`
    - [ ] `-d`
    - [ ] `expose_php=0`
    - [ ] `-d`
    - [ ] `display_errors=0`
    - [ ] `-d`
    - [ ] `html_errors=0`
    - [ ] `-d`
    - [ ] `log_errors=0`
    - [ ] `-S`
    - [ ] `<bind-host>:<port>`
    - [ ] `-t`
    - [ ] resolved target docroot
    - [ ] resolved target `index.php`
  - [ ] `-n` prevents ambient `php.ini`, scanned INI files, `auto_prepend_file`, and `auto_append_file` from changing the stub server
  - [ ] built-in-server child environment is inherited only after removing every environment key whose ASCII case-insensitive name equals `PHP_CLI_SERVER_WORKERS`
  - [ ] environment values are not used to select target, host, port, docroot, router, response, or process count
  - [ ] the built-in server always remains a single owned child process
  - [ ] MUST NOT use shell interpolation or a command string
  - [ ] child working directory is the repo root
  - [ ] child stdin/stdout/stderr use the exact platform-null descriptor contract
  - [ ] retains the child process handle
  - [ ] registers shutdown cleanup
  - [ ] forwards/handles supported termination signals where available
  - [ ] terminates and closes the child on shutdown
  - [ ] does not create a server log
  - [ ] does not include child output in diagnostics
  - [ ] normal mode without `--control-dir` prints exactly these readiness bytes:
    - [ ] `CORETSIA_SERVE_READY target=<target> bind=<bind-host>:<port> url=http://<connect-host>:<port>\n`
  - [ ] `--quiet` suppresses readiness stdout
  - [ ] when `--control-dir` is present, readiness is always published through the exact atomic ready-file protocol
  - [ ] `--quiet` does not suppress ready-file publication
  - [ ] `--quiet` suppresses only the readiness line; it does not suppress deterministic failure output
  - [ ] after readiness, the parent remains alive while the child server is running
  - [ ] while active, the parent polls:
    - [ ] built-in-server child status
    - [ ] the optional control-directory stop file
  - [ ] when no control directory exists, stdin has no command semantics
  - [ ] when a control directory exists, only the exact atomic stop-file protocol requests orderly shutdown
  - [ ] valid stop state:
    - [ ] is never written to stdout or stderr
    - [ ] triggers termination and closure of the built-in-server child
    - [ ] causes the serve wrapper to exit with status `0`
    - [ ] produces no failure payload
  - [ ] built-in-server child shutdown uses the bounded process-termination contract with timeout `5`
  - [ ] failure to terminate the built-in-server child after the hard-termination phase:
    - [ ] emits `CORETSIA_SERVE_INTERNAL_FAILURE`
    - [ ] exits with status `1`
  - [ ] unexpected child termination after readiness:
    - [ ] emits `{"schema":1,"code":"CORETSIA_SERVE_CHILD_EXITED"}\n` to stderr
    - [ ] exits with status `1`
  - [ ] orderly shutdown requested through the valid atomic stop-file protocol or a supported parent termination signal exits without failure output
  - [ ] failure output is one JSON line on stderr:
    - [ ] `{"schema":1,"code":"<CODE>"}\n`
  - [ ] failure output contains no host, port, path, process id, command, exception message, or child output

- [ ] `framework/bin/smoke`
  - [ ] pure-PHP deterministic smoke orchestrator
  - [ ] accepts exactly:
    - [ ] positional command `http`
    - [ ] `--target=<target>`
    - [ ] `--host=<host>`
    - [ ] `--port=<port>`
    - [ ] `--timeout=<seconds>`
  - [ ] defaults:
    - [ ] `target = web`
    - [ ] `host = 127.0.0.1`
    - [ ] `port = 8080`
    - [ ] `timeout = 5`
  - [ ] target validation occurs after syntactic argument parsing:
    - [ ] `web|api` are accepted HTTP targets
    - [ ] `console|worker` fail with `CORETSIA_SMOKE_TARGET_NOT_HTTP`
    - [ ] every other target fails with `CORETSIA_SMOKE_TARGET_INVALID`
  - [ ] target comparison is exact ASCII and case-sensitive
  - [ ] rejects `all|cli|db|config` and unknown commands deterministically
  - [ ] creates one private ephemeral control directory before process startup
  - [ ] immediately after successful directory creation, registers one idempotent cleanup boundary
  - [ ] the cleanup boundary is valid before any child process exists
  - [ ] every path after control-directory creation executes that cleanup boundary
  - [ ] starts exactly one serve child through an argument-vector `proc_open` command:
    - [ ] `PHP_BINARY`
    - [ ] repo-root `framework/bin/serve`
    - [ ] `--target=<target>`
    - [ ] `--host=<bind-host>`
    - [ ] `--port=<port>`
    - [ ] `--quiet`
    - [ ] `--control-dir=<absolute-control-directory>`
  - [ ] MUST NOT invoke the serve child through Composer
  - [ ] serve-child stdin/stdout/stderr use the platform null sink
  - [ ] expected readiness bytes are constructed exactly as:
    - [ ] `CORETSIA_SERVE_READY target=<target> bind=<bind-host>:<port> url=http://<connect-host>:<port>\n`
  - [ ] readiness polling checks:
    - [ ] serve-wrapper process status
    - [ ] existence and type of the `ready` file
    - [ ] exact byte contents of the `ready` file
  - [ ] readiness-file polling uses a fixed `50`-millisecond interval
  - [ ] readiness-file reading has a fixed `512`-byte budget
  - [ ] child exit before valid readiness maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] malformed, truncated, unexpected, oversized, or symlinked readiness state maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] after exact readiness-file validation, one TCP probe must succeed against the canonical connect host and selected port
  - [ ] failure of the post-readiness TCP probe maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] readiness timeout maps to `CORETSIA_SMOKE_SERVER_NOT_READY`
  - [ ] TCP reachability without the exact owned readiness file never establishes server ownership
  - [ ] executes exactly one checker child through an argument-vector `proc_open` command:
    - [ ] `PHP_BINARY`
    - [ ] repo-root `framework/bin/smoke-http`
    - [ ] `--host=<connect-host>`
    - [ ] `--port=<port>`
    - [ ] `--timeout=<timeout>`
  - [ ] checker stdin/stdout/stderr use the exact platform-null descriptor contract
  - [ ] no checker output pipe is created
  - [ ] checker exit status `0` means smoke success
  - [ ] any non-zero checker exit status maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker `proc_open` failure maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker child has a parent-enforced total process deadline of exactly:
    - [ ] `(2 * timeout) + 2` seconds
  - [ ] the deadline uses monotonic `hrtime(true)`
  - [ ] checker process status is polled every `50` milliseconds
  - [ ] checker timeout:
    - [ ] calls `proc_terminate`
    - [ ] waits for at most one additional `timeout` interval
    - [ ] maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker termination uses the bounded process-termination contract
  - [ ] checker remaining active after hard termination still maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] a checker that remains active after bounded hard termination:
    - [ ] prevents successful smoke completion
    - [ ] does not prevent serve-wrapper cleanup from executing
    - [ ] maps the pending smoke result to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker termination failure never bypasses serve-wrapper cleanup
  - [ ] checker process handle is closed only after the checker is inactive
  - [ ] checker timeout does not bypass serve cleanup
  - [ ] serve-wrapper `proc_open` failure maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] post-readiness serve-wrapper exit before checker completion maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] retains the serve-process handle
  - [ ] immediately after successful `proc_open`, attaches the serve-process handle to the already-registered cleanup boundary
  - [ ] serve-wrapper `proc_open` failure removes the owned control directory before emitting `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] cleanup executes for every post-spawn path:
    - [ ] readiness success
    - [ ] readiness timeout
    - [ ] serve-wrapper early exit
    - [ ] checker startup failure
    - [ ] checker failure
    - [ ] successful checker completion
    - [ ] unexpected caught failure
  - [ ] cleanup publishes `stop` with exact bytes `CORETSIA_SERVE_STOP\n` through the shared atomic IPC file-publication contract
  - [ ] if `stop.tmp` or `stop` already exists, or atomic publication otherwise fails:
    - [ ] orderly stop is skipped
    - [ ] bounded process termination begins
    - [ ] the publication failure itself is not emitted separately
  - [ ] cleanup waits up to the selected timeout for orderly wrapper termination
  - [ ] if the wrapper remains active, cleanup applies the bounded process-termination contract
  - [ ] failure to make the wrapper inactive after hard termination maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
  - [ ] no process output pipes exist
  - [ ] cleanup closes every process handle only after the corresponding process is inactive
  - [ ] cleanup captures the final serve-wrapper exit status before closing its process handle
  - [ ] after an established readiness handshake, serve-wrapper exit status must be exactly `0`
  - [ ] a non-zero serve-wrapper exit during cleanup maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
  - [ ] cleanup removes `ready`, `ready.tmp`, `stop`, and `stop.tmp`
  - [ ] cleanup removes the owned control directory
  - [ ] port-release verification is performed only when the exact readiness handshake was previously completed
  - [ ] when readiness was never established:
    - [ ] a reachable port is treated as externally owned
    - [ ] cleanup does not wait for that port to close
    - [ ] cleanup does not classify that external listener as an orphan
  - [ ] when readiness was established:
    - [ ] cleanup polls the canonical connect host and selected port for at most the selected timeout
    - [ ] the port must become unreachable
  - [ ] cleanup never waits without a fixed deadline
  - [ ] server cleanup failure occurs when any of the following is true:
    - [ ] the serve wrapper remains active after bounded termination attempts
    - [ ] an owned, readiness-confirmed port remains reachable
    - [ ] after an established readiness handshake, the serve wrapper exits with a non-zero status
    - [ ] any owned `ready`, `ready.tmp`, `stop`, or `stop.tmp` path cannot be removed
    - [ ] the owned control directory cannot be removed
  - [ ] server cleanup failure maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
  - [ ] cleanup failure takes precedence over every pending success or failure result
  - [ ] when cleanup succeeds, the previously selected success or failure result is preserved
  - [ ] smoke writes its final failure payload only after cleanup has completed
  - [ ] successful smoke completion requires:
    - [ ] the exact readiness handshake was completed
    - [ ] checker exited with status `0`
    - [ ] serve wrapper is no longer active
    - [ ] serve wrapper exited with status `0`
    - [ ] the readiness-confirmed port is no longer reachable
    - [ ] every owned IPC file and the control directory were removed
  - [ ] silent on success
  - [ ] deterministic failure codes:
    - [ ] `CORETSIA_SMOKE_INVALID_ARGUMENT`
    - [ ] `CORETSIA_SMOKE_TARGET_NOT_HTTP`
    - [ ] `CORETSIA_SMOKE_TARGET_INVALID`
    - [ ] `CORETSIA_SMOKE_SERVER_START_FAILED`
    - [ ] `CORETSIA_SMOKE_SERVER_NOT_READY`
    - [ ] `CORETSIA_SMOKE_HTTP_FAILED`
    - [ ] `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
    - [ ] `CORETSIA_SMOKE_INTERNAL_FAILURE`
  - [ ] failure output is exactly one JSON line on stderr:
    - [ ] `{"schema":1,"code":"<CODE>"}\n`
  - [ ] child stdout/stderr and child failure payloads are not forwarded
  - [ ] no profiles, env overrides, cURL, database checks, CLI checks, JUnit, GitHub annotations, colors, progress output, or dynamic logs remain

- [ ] `framework/bin/smoke-http`
  - [ ] pure-PHP checker only
  - [ ] MUST NOT start, stop, or manage a server
  - [ ] MUST NOT call `proc_open`
  - [ ] MUST NOT use ext-curl, cURL, HTTP clients, or external executables
  - [ ] CWD-independent repo-root discovery through `__DIR__`
  - [ ] loads `skeleton/bootstrap/HttpFrontController.php`
  - [ ] reads only `HttpFrontController::BOOT_NOT_READY_BODY`
  - [ ] MUST NOT call `HttpFrontController::run()`
  - [ ] accepts exactly:
    - [ ] `--host=<host>`
    - [ ] `--port=<port>`
    - [ ] `--timeout=<seconds>`
  - [ ] defaults:
    - [ ] host `127.0.0.1`
    - [ ] port `8080`
    - [ ] timeout `5`
  - [ ] request path is fixed to `/`
  - [ ] `--url`, `--path`, query, fragment, user-info, scheme selection, and arbitrary endpoint selection are unsupported
  - [ ] opens connection through `stream_socket_client`
  - [ ] connection establishment has one total `<timeout>`-second deadline
  - [ ] connection refusal, DNS-free connect failure, or connect timeout maps to `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
  - [ ] request write failure or incomplete request write maps to `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
  - [ ] after a successful request write, response reading has a new total `<timeout>`-second deadline
  - [ ] response-read timeout maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] socket warnings and platform diagnostics are never emitted
  - [ ] writes exact request:
    - [ ] `GET / HTTP/1.1`
    - [ ] deterministic `Host`
    - [ ] `Connection: close`
    - [ ] empty body
  - [ ] exact request bytes terminate with `\r\n\r\n`
  - [ ] `Host` is emitted exactly as `<connect-host>:<port>`
  - [ ] the original requested token `localhost` or `0.0.0.0` never appears in request bytes after canonicalization
  - [ ] no additional request headers are emitted
  - [ ] reads response with a fixed total response budget of `65536` bytes
  - [ ] status line plus complete header section, including terminating `\r\n\r\n`, has a separate maximum of `16384` bytes
  - [ ] exceeding either the header-section budget or total response budget maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] EOF before a complete HTTP header section maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] the header section must end with exact `\r\n\r\n`
  - [ ] status line grammar is exactly:
    - [ ] `HTTP/1.1 SP <three ASCII decimal digits> [SP <reason phrase>] CRLF`
  - [ ] reason phrase:
    - [ ] may be empty
    - [ ] otherwise contains only ASCII HTAB, SP, and visible bytes `0x21..0x7E`
  - [ ] status line contains no NUL, ESC, bare CR, bare LF, or another C0 control byte
  - [ ] malformed version, separators, status digits, or reason phrase maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] malformed status or header lines map to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] obsolete folded header lines are rejected
  - [ ] each header line grammar is exactly:
    - [ ] `<field-name>:<optional-field-value>\r\n`
  - [ ] `field-name` is immediately followed by `:`
  - [ ] whitespace before `:` is forbidden
  - [ ] optional leading and trailing field-value whitespace consists only of ASCII SP or HTAB
  - [ ] an empty field value is syntactically valid unless the specific required-header contract rejects it
  - [ ] a header line without `:` maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] header names must be non-empty ASCII HTTP token values
  - [ ] header values containing CR, LF, NUL, ESC, or another C0 control byte other than horizontal tab are rejected
  - [ ] required singleton headers occur exactly once:
    - [ ] `Content-Type`
    - [ ] `Cache-Control`
    - [ ] `Content-Length`
  - [ ] missing or duplicate required singleton headers map to `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
  - [ ] forbidden headers occur zero times:
    - [ ] `Set-Cookie`
    - [ ] `Location`
    - [ ] `X-Powered-By`
    - [ ] `Transfer-Encoding`
  - [ ] any forbidden-header occurrence maps to `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
  - [ ] `Content-Length`:
    - [ ] contains ASCII decimal digits only
    - [ ] contains no sign, whitespace, comma, decimal point, or exponent
    - [ ] is parsed without integer overflow
    - [ ] does not exceed `65536 - <status-and-header-section-byte-length>`
    - [ ] the subtraction is checked before use and cannot underflow
    - [ ] response reading never allocates a buffer based solely on the received `Content-Length`
  - [ ] body length must equal the parsed `Content-Length`
  - [ ] trailing bytes beyond `Content-Length` map to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] parses status line and headers deterministically
  - [ ] header names are compared ASCII case-insensitively
  - [ ] header values are compared byte-exactly after removing only optional leading and trailing ASCII SP/HTAB
  - [ ] internal whitespace is preserved
  - [ ] repeated header fields are never comma-joined before validation
  - [ ] failure validation order is exact:
    1. connection establishment and request write
    2. total response byte budget and header-section framing
    3. status-line grammar and individual header-line grammar
    4. required singleton header cardinality and forbidden-header absence
    5. `Content-Length` syntax, overflow, and response-budget validation
    6. body framing against the parsed `Content-Length`
    7. expected status code
    8. required header values
    9. expected body bytes
  - [ ] the first failed stage determines the only emitted failure code
  - [ ] exact mappings:
    - [ ] stage 1 → `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
    - [ ] stages 2, 3, 5, or 6 → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] stage 4 or 8 → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] stage 7 → `CORETSIA_SMOKE_HTTP_STATUS_MISMATCH`
    - [ ] stage 9 → `CORETSIA_SMOKE_HTTP_BODY_MISMATCH`
  - [ ] asserts:
    - [ ] status exactly `503`
    - [ ] `Content-Type` exactly `application/json; charset=utf-8`
    - [ ] `Cache-Control` exactly `no-store`
    - [ ] `Content-Length` value is exactly the canonical unsigned base-10 ASCII representation of `strlen(BOOT_NOT_READY_BODY)`
    - [ ] the expected `Content-Length` contains no leading zero
    - [ ] numeric equality with a non-canonical representation is insufficient
    - [ ] no `Set-Cookie`
    - [ ] no `Location`
    - [ ] no `X-Powered-By`
    - [ ] no `Transfer-Encoding`
    - [ ] body equals `BOOT_NOT_READY_BODY` byte-for-byte
    - [ ] body ends with exactly one LF
  - [ ] does not accept URL, path, query, arbitrary expected status, regex, headers, body, method, request headers, or payload file
  - [ ] silent on success
  - [ ] deterministic failure codes:
    - [ ] `CORETSIA_SMOKE_HTTP_INVALID_ARGUMENT`
    - [ ] `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
    - [ ] `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] `CORETSIA_SMOKE_HTTP_STATUS_MISMATCH`
    - [ ] `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] `CORETSIA_SMOKE_HTTP_BODY_MISMATCH`
    - [ ] `CORETSIA_SMOKE_HTTP_INTERNAL_FAILURE`
  - [ ] failure output is one JSON line on stderr:
    - [ ] `{"schema":1,"code":"<CODE>"}\n`
  - [ ] failure output contains no URL, host, port, response body, response header value, path, exception message, or socket diagnostic

#### Modifies

- [ ] repo-root `composer.json` — add scripts:
  - [ ] `serve` → `@php framework/bin/serve`
  - [ ] `smoke:http` → `@php framework/bin/smoke http`
  - [ ] script arguments remain pass-through after Composer `--`

- [ ] `docs/architecture/PACKAGING.md`
  - [ ] register stable HTTP entrypoints:
    - [ ] `skeleton/apps/web/public/index.php`
    - [ ] `skeleton/apps/api/public/index.php`
  - [ ] document explicit front-controller target ownership
  - [ ] document shared non-public `HttpFrontController` bootstrap seam
  - [ ] document that console and worker use non-HTTP host entrypoints owned by their respective epics
  - [ ] document that HTTP front-controller paths remain stable when Phase 3 replaces the temporary fallback

### Verification (TEST EVIDENCE) (MUST)

- [ ] `composer serve -- --target=web`
  - [ ] selects `skeleton/apps/web/public`
  - [ ] serves the web front controller
  - [ ] returns deterministic 503 fallback

- [ ] `composer serve -- --target=api`
  - [ ] selects `skeleton/apps/api/public`
  - [ ] serves the API front controller
  - [ ] returns the same deterministic 503 fallback

- [ ] `composer smoke:http -- --target=web`
  - [ ] starts one server
  - [ ] verifies exact response
  - [ ] terminates the server
  - [ ] succeeds silently

- [ ] `composer smoke:http -- --target=api`
  - [ ] starts one server
  - [ ] verifies exact response
  - [ ] terminates the server
  - [ ] succeeds silently

- [ ] direct `framework/bin/smoke-http` without a server:
  - [ ] fails with `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
  - [ ] starts no server

- [ ] occupied serve port:
  - [ ] fails with `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] emits only exact deterministic JSON failure output

### Tests (MUST)

- Contract:
  - [ ] `framework/tools/tests/Contract/RepoRootHttpSmokeComposerScriptsContractTest.php`
    - [ ] repo-root `composer.json` contains exact `serve` script:
      - [ ] `@php framework/bin/serve`
    - [ ] repo-root `composer.json` contains exact `smoke:http` script:
      - [ ] `@php framework/bin/smoke http`
    - [ ] no duplicate or alternative serve/smoke script entry exists
    - [ ] scripts contain no shell operator, platform-specific executable, env assignment, or CWD-dependent path

  - [ ] `framework/tools/tests/Contract/HttpSmokeEphemeralIpcContractTest.php`
    - [ ] smoke/serve IPC does not rely on non-blocking `proc_open` pipes
    - [ ] no `stream_select()` call receives a process pipe
    - [ ] readiness uses only the atomic ready-file protocol
    - [ ] orderly stop uses only the atomic stop-file protocol
    - [ ] IPC directory and filenames match the exact contract
    - [ ] IPC paths are absent from public diagnostics
    - [ ] temporary IPC state is not classified as a generated artifact

  - [ ] `framework/tools/tests/Contract/HttpFrontControllerFallbackContractTest.php`
    - [ ] class file can be required without producing output or headers
    - [ ] class is final
    - [ ] exact constant value is independently asserted as:
      - [ ] `{"schema":1,"code":"CORETSIA_HTTP_BOOT_NOT_READY","message":"boot not ready"}\n`
    - [ ] constant is ASCII-only
    - [ ] constant contains LF only
    - [ ] constant ends with exactly one LF
    - [ ] constant contains no target, timestamp, host, port, path, request, process, or exception data
    - [ ] `run('invalid')` throws exactly `LogicException('http-front-controller-target-invalid')`
    - [ ] invalid-target exception contains no rejected target value

  - [ ] `framework/tools/tests/Contract/HttpSkeletonFrontControllersDeclareCanonicalTargetsTest.php`
    - [ ] web front controller calls shared bootstrap with exactly `web`
    - [ ] api front controller calls shared bootstrap with exactly `api`
    - [ ] both use CWD-independent paths
    - [ ] neither front controller reads superglobals
    - [ ] neither duplicates fallback response bytes
    - [ ] no HTTP front controller exists under console or worker skeleton apps

  - [ ] `framework/tools/tests/Contract/SmokeHttpIsSocketCheckerOnlyTest.php`
    - [ ] rejects ext-curl and cURL symbols
    - [ ] rejects `proc_open`
    - [ ] rejects env reads
    - [ ] rejects server-start, URL, path, query, regex, preview, arbitrary-header, and expected-payload options
    - [ ] checker reads the canonical bootstrap body constant

- Integration:
  - [ ] `framework/tools/tests/Integration/ServeSmokeAndSmokeHttpCliContractTest.php`
    - [ ] split option forms are rejected
    - [ ] duplicate options are rejected
    - [ ] unknown options are rejected
    - [ ] empty values are rejected
    - [ ] invalid host, port, and timeout values are rejected
    - [ ] `localhost` canonicalizes to `127.0.0.1`
    - [ ] `0.0.0.0` uses bind host `0.0.0.0` and connect host `127.0.0.1`
    - [ ] `console|worker` map to the exact target-not-HTTP codes
    - [ ] unknown targets map to the exact target-invalid codes
    - [ ] every failure exits with status `1`
    - [ ] every failure emits exactly one safe JSON line
    - [ ] the shared option grammar is verified independently for all three scripts
    - [ ] `serve` and `smoke` reject invalid target case variants
    - [ ] `smoke-http` rejects `--url` and `--path`
    - [ ] every script works from a non-repository current working directory
    - [ ] hostile or similarly named env values do not alter defaults or parsing
    - [ ] internal failure output contains only the script-local internal code

  - [ ] `framework/tools/tests/Integration/ServeLifecycleControlTest.php`
    - [ ] serve emits readiness only after TCP reachability
    - [ ] exact atomic stop-file protocol terminates the wrapper and built-in server
    - [ ] orderly control shutdown exits with status `0`
    - [ ] orderly control shutdown emits no failure output
    - [ ] selected port is no longer reachable after successful shutdown
    - [ ] built-in-server stdin uses the platform null sink
    - [ ] canonical `ready` and valid `stop` files are regular files rather than symlinks
    - [ ] pre-existing reserved IPC files or symlinks cause `CORETSIA_SERVE_INVALID_ARGUMENT`
    - [ ] malformed or symlinked `stop` state causes `CORETSIA_SERVE_INTERNAL_FAILURE`
    - [ ] smoke-side readiness validation rejects any non-canonical ready-file state before TCP ownership confirmation
    - [ ] control-directory paths never appear in output
    - [ ] `serve` does not delete the caller-owned control directory
    - [ ] after orderly serve shutdown, the control directory contains no unexpected paths beyond the reserved IPC filenames
    - [ ] the test owner can remove all remaining reserved IPC files and the control directory
    - [ ] atomic `ready` file contains exactly one LF-terminated readiness line
    - [ ] atomic `ready` file uses canonical bind and connect hosts
    - [ ] direct serve without `--control-dir` emits the same readiness bytes to stdout
    - [ ] unexpected post-readiness child exit maps to `CORETSIA_SERVE_CHILD_EXITED`

  - [ ] `framework/tools/tests/Integration/ServeIgnoresAmbientPhpConfigurationTest.php`
    - [ ] runs with a synthetic hostile `PHPRC`
    - [ ] runs with a synthetic hostile `PHP_INI_SCAN_DIR`
    - [ ] hostile INI attempts to configure:
      - [ ] `auto_prepend_file`
      - [ ] `auto_append_file`
      - [ ] `display_errors`
      - [ ] `expose_php`
    - [ ] runs with `PHP_CLI_SERVER_WORKERS=4`
    - [ ] response remains the exact canonical fallback
    - [ ] no prepend/append bytes appear
    - [ ] no `X-Powered-By` appears
    - [ ] `PHP_CLI_SERVER_WORKERS` does not change readiness, response, or shutdown behavior
    - [ ] orderly shutdown leaves no listener on the selected port
    - [ ] cleanup leaves no IPC file or control directory

  - [ ] `framework/tools/tests/Integration/HttpStubSmokeTest.php`
    - [ ] matrix:
      - [ ] target `web`
      - [ ] target `api`
    - [ ] invokes `framework/bin/smoke http --target=<target>`
    - [ ] verifies status, required headers, forbidden headers, content length, and exact body bytes
    - [ ] verifies silent success
    - [ ] verifies server process is terminated after the smoke run
    - [ ] verifies every owned IPC file and the smoke control directory are removed after the smoke run
    - [ ] every normal success and checker-failure path performs cleanup
    - [ ] final output is emitted only after cleanup
    - [ ] cleanup failure overrides an earlier success or failure result with `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`

  - [ ] `framework/tools/tests/Integration/SmokeHttpDoesNotManageServerTest.php`
    - [ ] without a running server, `smoke-http` fails with `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
    - [ ] no server process is started
    - [ ] no log or temporary diagnostic file is created

  - [ ] `framework/tools/tests/Integration/SmokeHttpFailureMappingTest.php`
    - [ ] uses a synthetic local socket responder with fixed response fixtures
    - [ ] malformed status line → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] incomplete header section → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] response over `65536` bytes → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] status/header section over `16384` bytes → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] `Content-Length` larger than the remaining total response budget → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] wrong status → `CORETSIA_SMOKE_HTTP_STATUS_MISMATCH`
    - [ ] missing or duplicate required header → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] forbidden header → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] malformed `Content-Length` → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] body-length mismatch → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] wrong body bytes → `CORETSIA_SMOKE_HTTP_BODY_MISMATCH`
    - [ ] every failure output contains no raw response data
    - [ ] missing `Content-Length` → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] duplicate `Content-Length` → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] syntactically malformed single `Content-Length` → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] numerically correct but non-canonical `Content-Length` representation → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] whitespace before a header colon → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] missing header colon → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] header mismatch takes precedence over body comparison
    - [ ] status mismatch takes precedence over header-value and body mismatch
    - [ ] response framing failure takes precedence over every semantic mismatch
    - [ ] response-read timeout → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`

  - [ ] `framework/tools/tests/Integration/ServePortUnavailableDeterministicTest.php`
    - [ ] starts `framework/bin/serve` on an already occupied port
    - [ ] asserts `CORETSIA_SERVE_PORT_UNAVAILABLE`
    - [ ] asserts exact one-line JSON failure output
    - [ ] asserts no child output, path, host, port, PID, command, or exception diagnostic is exposed

  - [ ] `framework/tools/tests/Integration/SmokeOccupiedPortDoesNotEstablishReadinessTest.php`
    - [ ] starts an unrelated synthetic listener on the selected port
    - [ ] invokes `framework/bin/smoke http`
    - [ ] external TCP reachability is not accepted as smoke readiness
    - [ ] smoke never invokes `smoke-http` against the unrelated listener
    - [ ] failure maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
    - [ ] the unrelated listener remains active and is not treated as an owned orphan
    - [ ] cleanup does not wait for the unrelated listener to terminate

### DoD (MUST)

- [ ] Web and API have real stable public front-controller paths.
- [ ] Each front controller declares exactly one canonical target.
- [ ] No target is inferred from path, request, env, config, host, or command-line runtime data.
- [ ] `HttpFrontController` is outside public docroots.
- [ ] The temporary 503 response is owned by the shared HTTP bootstrap seam.
- [ ] `_boot_not_ready_payload.php` does not exist.
- [ ] Web and API return the same byte-exact fallback body.
- [ ] The fallback body is encoded statically and not generated at request time.
- [ ] Status is exactly 503.
- [ ] Required headers are deterministic.
- [ ] Cookies, redirects, `X-Powered-By`, request reflection, and dynamic diagnostics are absent.
- [ ] No superglobal is read by the Phase-2 fallback.
- [ ] `serve` and `smoke http` use canonical `--target`.
- [ ] `serve` accepts only HTTP targets `web|api`.
- [ ] `console|worker` are rejected deterministically and receive no HTTP entrypoints.
- [ ] `smoke` starts exactly one server and always terminates it.
- [ ] `smoke-http` is a checker only and never starts a server.
- [ ] No cURL or external HTTP client dependency remains.
- [ ] No env-controlled smoke behavior remains.
- [ ] No profiles, arbitrary expectations, dynamic logs, previews, colors, JUnit, or GitHub annotations remain.
- [ ] Success paths are silent except for the optional deterministic `serve` readiness line.
- [ ] Failure output is a fixed one-line JSON shape containing only schema and code.
- [ ] Port collision produces `CORETSIA_SERVE_PORT_UNAVAILABLE`.
- [ ] All scripts are CWD-independent.
- [ ] Child process commands use argument vectors without shell interpolation.
- [ ] A successful smoke run leaves no serve wrapper or PHP built-in server process.
- [ ] Cleanup has fixed deadlines and reports `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED` instead of waiting indefinitely.
- [ ] No default skeleton `config/http.php` is introduced.
- [ ] HTTP front controllers are classified as executable app entrypoints, not default HTTP configuration
- [ ] `framework/tools/gates/no_skeleton_http_default_gate.php` remains unchanged
- [ ] No gate allowlist or front-controller exception is introduced
- [ ] Front-controller paths can remain unchanged when platform/http replaces the temporary fallback.
- [ ] `smoke`, `smoke-http`, and `serve` are created by this epic rather than treated as existing Coretsia files.
- [ ] Imported source code establishes no compatibility surface.
- [ ] CLI option grammar, host, port, timeout, duplicate-option, and exit-status behavior are deterministic.
- [ ] `serve` emits readiness only after the target port accepts connections.
- [ ] `smoke` suppresses all child diagnostics and emits only its own deterministic failure payload.
- [ ] Exact fallback bytes are independently protected by a contract test rather than verified only through the shared constant.
- [ ] HTTP response parsing has a fixed byte budget and rejects malformed or ambiguous framing.
- [ ] `smoke` accepts readiness only from the exact serve-wrapper readiness handshake plus TCP reachability.
- [ ] A pre-existing listener cannot be mistaken for the smoke-owned PHP server.
- [ ] Port-release verification is performed only for a readiness-confirmed owned server.
- [ ] Every post-spawn smoke path performs bounded cleanup before final output.
- [ ] Cleanup failure has deterministic precedence over every prior smoke result.
- [ ] Built-in-server stdin uses the platform null sink and serve orchestration control exists only through atomic filesystem IPC.
- [ ] Process descriptor mappings and platform null sinks are exact and cross-OS.
- [ ] Every CLI script has a safe internal-failure code and one top-level failure boundary.
- [ ] Socket connect, request-write, response-read, parsing, status, header, and body failures have exact precedence.
- [ ] Repo-root Composer serve and HTTP-smoke entrypoints are protected by a contract test.
- [ ] `docs/architecture/PACKAGING.md` documents the final target-aware front-controller and bootstrap ownership model.
- [ ] Cross-OS orchestration does not depend on non-blocking `proc_open` pipes or `stream_select()` over process descriptors.
- [ ] Smoke/serve readiness and stop control use private atomic ephemeral filesystem IPC.
- [ ] Every owned IPC file and directory is removed after success or failure.
- [ ] Built-in-server execution ignores ambient php.ini, scanned INI files, prepend/append files, and `PHP_CLI_SERVER_WORKERS`.
- [ ] Windows process creation explicitly bypasses `cmd.exe`.
- [ ] The checker child has a parent-enforced monotonic deadline.
- [ ] Required-header cardinality is validated before parsing and applying `Content-Length`.
- [ ] HTTP failure precedence is executable without circular or unavailable input dependencies.
- [ ] IPC cleanup is registered immediately after control-directory creation, before process startup.
- [ ] Atomic IPC publication uses exclusive temporary files, complete writes, flush, close, and same-directory rename.
- [ ] Serve, checker, and wrapper termination have bounded graceful and hard-termination phases.
- [ ] Successful smoke completion requires serve-wrapper exit status `0`.
- [ ] A non-zero wrapper exit during cleanup maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`.
- [ ] Total HTTP response budget includes status line, headers, separator, and body.
- [ ] `Content-Length` cannot exceed the remaining total response budget.
- [ ] No stale stdin-pipe or readiness-output terminology remains after adoption of filesystem IPC.
- [ ] The shared atomic IPC publication contract is referenced rather than partially duplicated in smoke cleanup.
- [ ] Server cleanup failure triggers include process state, wrapper exit status, owned port state, and IPC removal.
- [ ] IPC directory deletion remains owned by `smoke`, not by `serve`.
- [ ] `framework/bin/smoke` accepts only the exact layout `smoke http [options]`.
- [ ] HTTP `Host` uses the canonical connect host rather than the original requested host token.
- [ ] HTTP header-line colon and whitespace grammar is deterministic.
- [ ] Expected `Content-Length` uses one canonical decimal byte representation.
- [ ] All contract and integration tests pass.

---

### 2.60.0 Devtools CLI-spikes — Tag-first Source-host Module Migration (MUST) [IMPL]

---
type: package
phase: 2
epic_id: "2.60.0"
owner_path: "framework/packages/devtools/cli-spikes/"

package_id: "devtools/cli-spikes"
composer: "coretsia/devtools-cli-spikes"
kind: runtime
module_id: "devtools.cli-spikes"

goal: "Мігрувати devtools/cli-spikes з Phase-0 `cli.commands` registry до звичайного enabled-module/provider внеску через tag-first `cli.command`, з exact six-key metadata, без platform/cli concrete imports і без змішування tools-only spike logic із runtime CLI host."
provides:
- "Dev-only installed package that is visible to Kernel source-host module/provider planning only when explicitly enabled by a mode preset"
- "Dual-interface source-host-capable provider contributing lazy `cli.command` services"
- "Exact six-key command metadata compatible with 2.30.0"
- "No legacy config command registry"
- "No platform/cli namespace dependency from command-owner source"
- "Preserved thin-adapter boundary to `framework/tools/spikes/**`"
- "One canonical `workspace:sync` command instead of duplicate signature-encoded command identities"
- "No collision with the platform-owned `doctor` command"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-devtools-cli-spikes-module-enablement.md"
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/modules-and-manifests.md"
- "docs/ssot/modes.md"
- "docs/ssot/runtime-container-definitions.md"
- "docs/ssot/sensitive-data-redaction.md"
---

### Existing package replacement boundary (MUST)

The existing Phase-0 implementation is not a compatibility baseline for discovery, command identity, metadata, help, output-format options, or CLI base error codes.

This epic removes:

- `cli.commands` FQCN registry configuration;
- direct dependency on `coretsia/platform-cli`;
- imports from `Coretsia\Platform\Cli\*`;
- dependency on the deleted platform CLI `ErrorCodes` registry;
- command names containing arguments or options;
- the duplicate `workspace:sync --dry-run` and `workspace:sync --apply` service identities;
- the devtools-owned `doctor` name collision;
- command-local `--json`, `--text`, `--format`, `--help`, and `-h` presentation behavior where final formatting/help is owned by 2.30.0.

No compatibility adapters, alias commands, deprecated wrappers, dual discovery, or fallback reads from `cli.commands` may remain.

### Source-host visibility decision (MUST)

The package remains dev-only by installation policy but becomes a Coretsia runtime module for source-host composition.

These concepts MUST remain distinct:

```text
Composer package type = library
installation policy = require-dev / development environment only
Coretsia extra.coretsia.kind = runtime
module enablement = explicit mode preset membership
production default modes = do not enable devtools.cli-spikes
```

A package with `extra.coretsia.kind = library` and no `moduleId` is intentionally ignored by `ComposerManifestReader` and cannot contribute a provider to `KernelOpsHostBooter`.

Therefore the previous requirement to remain non-module while being discoverable through the source-host TagRegistry is removed as internally contradictory.

The package MUST NOT be enabled by any framework-default mode preset.

It becomes available only when:

- the package is installed;
- the selected source-host mode preset explicitly includes `devtools.cli-spikes`;
- `platform.cli` is enabled;
- the canonical provider plan includes `CliSpikesServiceProvider`.

### Development activation policy (MUST)

Package installation and module enablement are intentionally independent.

Installing `coretsia/devtools-cli-spikes` makes its module manifest and classes available to Composer-backed discovery. It MUST NOT, by itself, add providers or commands to the selected source host.

The selected mode preset remains the only module-selection authority.

This explicit activation boundary prevents:

- an installed but unused devtools package from changing the command catalog;
- `appEnv`, `debug`, or raw Composer presence from becoming a second module-selection policy;
- accidental command exposure when development dependencies are present in a production filesystem;
- conditional provider registration inside `platform/cli` or `CliSpikesServiceProvider`.

Canonical development activation is explicit project-owned or fixture-owned configuration created outside the distributed default skeleton state.

This epic MUST NOT add a mode preset, module-selection file, or CLI-spikes activation to the repo-root default skeleton.

Example `<development-skeleton-root>/config/app.php`:

```php
return [
    'presets' => [
        'console' => 'devtools-console',
    ],
];
```

Example `<development-skeleton-root>/config/modes/devtools-console.php`:

```php
return [
    'schemaVersion' => 1,
    'name' => 'devtools-console',
    'description' => 'Coretsia monorepo development CLI.',
    'required' => [
        'core.foundation',
        'core.kernel',
        'platform.cli',
        'devtools.cli-spikes',
    ],
    'optional' => [],
    'disabled' => [],
    'featureBundles' => [],
    'metadata' => [],
];
```

- these examples describe post-creation project-owned development configuration
- these example files are not deliverables under repo-root `skeleton/`
- the package-local `SourceHostApp` fixture is the canonical automated proof
- repo-root `skeleton/config/modes/*.php` remains absent by default
- repo-root `skeleton/config/modules.php` remains absent
- `skeleton/apps/*/config/modules.php` remains absent
- no exception or allowlist is added to any `no_skeleton_*` gate

Rules:

- `skeleton/config/app.php` selects only the console preset;
- the preset owns module selection;
- the configuration is created once per development skeleton, not per command invocation;
- package presence MUST NOT auto-enable the module;
- `debug` MUST NOT enable or disable the module;
- `appEnv` MUST NOT enable or disable the module;
- `platform/cli` config MUST NOT enable or disable the module;
- no `cli.devtools.enabled` or `cli.spikes.enabled` key is introduced;
- `CliSpikesServiceProvider` MUST NOT conditionally register commands;
- production console presets MUST NOT include `devtools.cli-spikes`;
- production distributions SHOULD omit the package through Composer `--no-dev`.

Zero-config activation, if introduced later, MUST be a generic entrypoint/profile capability specified in a separate epic. It MUST NOT be implemented as package-specific detection in this epic.

### Dependencies (MUST)

#### Preconditions (MUST)

- 2.27.0 is implemented:
  - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` exists;
  - the canonical platform redaction policy is documented in `docs/ssot/sensitive-data-redaction.md`;
  - platform/cli final output rendering owns defense-in-depth redaction.
- 2.30.0 is implemented.
- `ReservedTags::CLI_COMMAND` exists in `core/foundation`.
- `cli.command` is owned by `platform/cli` in `docs/ssot/tags.md`.
- exact metadata keys are:
  - `name`;
  - `summary`;
  - `group`;
  - `hidden`;
  - `arguments`;
  - `options`.
- every tagged service id is the exact command class FQCN.
- tag priority is actual `0` and metadata does not contain `priority`.
- source-host selected providers must implement both:
  - `ServiceProviderInterface`;
  - `ContainerDefinitionProviderInterface`.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `core/kernel` imports;
- `platform/*` imports;
- `integrations/*` imports;
- concrete platform/cli input, output, catalog, runner, formatter, exception, or error-code classes;
- raw `cli.command` string literals in production source;
- filesystem scanning for command discovery;
- reflection-based command construction;
- direct stdout/stderr writes.
- package-local redaction engines, policies, classifiers, pattern registries, or hashers;
- a second runtime sensitive-key or sensitive-value classification model;
- concrete `platform/redaction` implementation imports;
- direct redactor resolution or invocation from devtools command adapters;

The owner package contributes through the documented Foundation reserved-tag extension point and therefore uses:

```text
Coretsia\Foundation\Tag\ReservedTags::CLI_COMMAND
```

No package-local mirror `ReservedTags` class is introduced.

### Cross-package modification boundary (MUST)

The only files outside `framework/packages/devtools/cli-spikes/` that this epic may create or modify are:

- `docs/adr/ADR-XXXX-devtools-cli-spikes-module-enablement.md`
- `docs/adr/INDEX.md`
- `docs/architecture/PACKAGING.md`
- `framework/tools/gates/package_compliance_gate.php`
- `framework/tools/tests/Integration/PackageComplianceGateConfiglessRuntimePolicyTest.php`

These cross-package changes exist only to establish the generic configless-runtime-package policy.

This epic MUST NOT modify:

- `platform/cli` production source;
- Kernel module-selection implementation;
- framework-default mode presets;
- repo-root default skeleton mode presets;
- repo-root or app-local `modules.php`;
- monorepo CLI wrappers;
- Bootstrap Phase A schema or resolution precedence;
- `framework/tools/gates/no_skeleton_mode_presets_default_gate.php`;
- `framework/tools/gates/no_skeleton_modules_default_gate.php`;
- `framework/tools/config/package_compliance_allowlist.php`.

No package-specific package-compliance exception or allowlist entry is permitted.

### Module metadata and mode ownership (MUST)

Canonical module id:

```text
devtools.cli-spikes
```

Canonical module graph requirements:

```text
core.foundation
platform.cli
```

The package MUST NOT be added to:

- `micro` framework default mode;
- `express` framework default mode;
- `hybrid` framework default mode;
- `enterprise` framework default mode.

Tests and package-local development fixtures MUST use an explicit fixture-owned mode preset containing `devtools.cli-spikes`.

The fixture represents a project-owned post-creation development skeleton. It MUST NOT be copied into or treated as part of the repo-root distributed default skeleton.

Production applications that do not install or enable this package observe no command catalog or provider change.

### Canonical command surface (MUST)

The final command set is exactly:

```text
spike:doctor
spike:fingerprint
spike:config:debug
deptrac:graph
workspace:sync
```

No command is named `doctor` because that name is owned by the platform CLI built-in ultra-early command.

No command name contains spaces or option syntax.

Every command class exposes exactly:

```php
public const string NAME;
public const string SUMMARY;
public const string GROUP;
public const bool HIDDEN;
public const array ARGUMENTS;
public const array OPTIONS;
```

Every `name()` method returns `self::NAME`.

All five commands use the base catalog group:

```text
devtools
```

The group is presentation metadata only.

It MUST NOT control:

- package installation;
- module enablement;
- provider inclusion;
- command authorization;
- production availability.

The final catalog view MAY change the displayed group only through the existing validated `cli.commands.overrides` mechanism.

#### Exact command metadata (MUST)

`SpikeDoctorCommand`:

```text
NAME = spike:doctor
SUMMARY = Run tools-only spike diagnostics.
GROUP = devtools
HIDDEN = false
ARGUMENTS = []
OPTIONS = []
```

`SpikeFingerprintCommand`:

```text
NAME = spike:fingerprint
SUMMARY = Run the deterministic fingerprint spike.
GROUP = devtools
HIDDEN = false
ARGUMENTS = []
OPTIONS = []
```

`SpikeConfigDebugCommand`:

```text
NAME = spike:config:debug
SUMMARY = Show deterministic config-merge spike diagnostics.
GROUP = devtools
HIDDEN = false
ARGUMENTS = []
OPTIONS:
  key       value=required repeatable=false summary="Config dot key."
  scenario  value=optional repeatable=false summary="Spike scenario id."
```

`DeptracGraphCommand`:

```text
NAME = deptrac:graph
SUMMARY = Build deterministic deptrac graph artifacts from a spike fixture.
GROUP = devtools
HIDDEN = false
ARGUMENTS = []
OPTIONS:
  fixture  value=optional repeatable=false summary="Fixture path relative to the deptrac_min fixture root."
  out      value=optional repeatable=false summary="Repository-relative output directory."
```

`WorkspaceSyncCommand`:

```text
NAME = workspace:sync
SUMMARY = Run deterministic workspace synchronization.
GROUP = devtools
HIDDEN = false
ARGUMENTS = []
OPTIONS:
  dry-run  value=none     repeatable=false summary="Calculate changes without writing them."
  apply    value=none     repeatable=false summary="Apply calculated workspace changes."
  fixture  value=optional repeatable=false summary="Fixture name."
```

`WorkspaceSyncCommand` domain validation requires exactly one of:

```text
--dry-run
--apply
```

The options are mutually exclusive.

The structural validator owns undeclared, repeated, and missing-value option rejection.

The command owns only the exact-one-mode domain rule and fixture-name domain validation.

Command-local formatting options are forbidden:

```text
--json
--text
--format
--color
```

Final `json|table|plain` selection remains owned by platform/cli global options and output policy.

Command-local help options are removed. Help is generated from the final CommandCatalog through:

```text
coretsia help <command>
```

### Error and exception ownership (MUST)

The package MUST NOT use deleted or concrete platform CLI error-code classes.

Creates one package-owned code registry:

```text
Coretsia\Devtools\CliSpikes\Diagnostics\CliSpikesDiagnosticCodes
```

Exact generic codes:

```text
CORETSIA_CLI_SPIKES_INPUT_INVALID
CORETSIA_CLI_SPIKES_EXECUTION_FAILED
```

Rules:

- domain input rejection uses `CORETSIA_CLI_SPIKES_INPUT_INVALID` with a fixed safe reason token;
- missing tools-only workflow classes or invalid returned tool shapes use `CORETSIA_CLI_SPIKES_EXECUTION_FAILED` with a fixed safe reason token;
- known tools-only `DeterministicException` codes MAY be translated only through an immutable package-owned allowlist:
  - key = exact documented tool diagnostic code
  - value = fixed public safe message
- the Throwable message MUST NOT be forwarded, copied, concatenated, or used as fallback output
- an unlisted `DeterministicException` MUST propagate unchanged to the canonical CLI error boundary
- `SpikesBootstrapFailedException::reason()` MAY be translated only through an immutable allowlist of fixed safe reasons
- an unknown bootstrap reason MUST NOT be rendered and the exception propagates to the canonical CLI error boundary
- unexpected Throwables MUST NOT be caught and downgraded by command adapters;
- unexpected Throwables propagate to the canonical `CommandRunner` / `CliApplication` error boundary;
- previous Throwable messages, stack traces, absolute paths, raw config, and raw payloads are forbidden.

### Deliverables (MUST)

#### Creates

Module/provider:
- [ ] `framework/packages/devtools/cli-spikes/src/Module/CliSpikesModule.php`
  - [ ] canonical constants:
    - [ ] `MODULE_ID = 'devtools.cli-spikes'`
    - [ ] `PACKAGE_ID = 'devtools/cli-spikes'`
    - [ ] `COMPOSER_PACKAGE = 'coretsia/devtools-cli-spikes'`
    - [ ] `KIND = 'runtime'`
  - [ ] returns exactly `CliSpikesServiceProvider::class`
  - [ ] performs no discovery, config loading, command execution, or tools bootstrap

- [ ] `framework/packages/devtools/cli-spikes/src/Provider/CliSpikesServiceProvider.php`
  - [ ] implements `ServiceProviderInterface`
  - [ ] implements `ContainerDefinitionProviderInterface`
  - [ ] contributes exactly the five final command services
  - [ ] contributes exactly five `ReservedTags::CLI_COMMAND` tags
  - [ ] every service id is the exact command class FQCN
  - [ ] every tag uses actual priority `0`
  - [ ] metadata contains exactly six required keys
  - [ ] metadata references command class constants only
  - [ ] no config reads during registration or definition contribution
  - [ ] no command service is instantiated while provider definitions are collected
  - [ ] MUST NOT invoke `SpikesBootstrap`
  - [ ] MUST NOT resolve `SpikesPaths`
  - [ ] MUST NOT inspect `framework/tools/spikes/**`
  - [ ] MUST NOT check whether tools-only bootstrap files exist
  - [ ] command catalog, `list`, and `help` MUST remain available without loading tools-only runtime
  - [ ] tools-only bootstrap occurs only after lazy resolution of the selected devtools command and inside that command’s normal CLI UoW
  - [ ] source `register()` and canonical `define()` contributions are semantically identical

- [ ] `framework/packages/devtools/cli-spikes/src/Diagnostics/CliSpikesDiagnosticCodes.php`
  - [ ] contains only the two canonical package-owned codes
  - [ ] deterministic `all()` order if an enumerator is provided
  - [ ] no dependency on platform/cli

Commands:
- [ ] `framework/packages/devtools/cli-spikes/src/Command/SpikeDoctorCommand.php`
  - [ ] replaces the old conflicting `DoctorCommand`
  - [ ] remains a thin adapter to tools-only spike diagnostics
  - [ ] uses exact final metadata

- [ ] `framework/packages/devtools/cli-spikes/src/Command/WorkspaceSyncCommand.php`
  - [ ] replaces both old workspace sync command classes
  - [ ] uses one canonical command identity
  - [ ] implements exact-one-mode validation
  - [ ] dispatches to the same tools-only workspace entry workflow
  - [ ] emits output only through `OutputInterface`

Docs:
- [ ] `docs/adr/ADR-XXXX-devtools-cli-spikes-module-enablement.md`
  - [ ] records installation versus module enablement separation
  - [ ] records explicit mode-preset activation
  - [ ] rejects appEnv/debug/package-presence auto-activation
  - [ ] records no framework-default preset membership
  - [ ] records the distinction between:
    - [ ] distributed default skeleton
    - [ ] project-owned post-creation development skeleton
    - [ ] package-local synthetic test fixture
  - [ ] records that no repo-root skeleton preset or modules file is introduced
  - [ ] records that no `no_skeleton_*` gate exception is introduced
  - [ ] records the generic configless runtime package policy
  - [ ] records canonical module id `devtools.cli-spikes`
  - [ ] records production `--no-dev` as defense in depth

Test fixture:
- [ ] `framework/packages/devtools/cli-spikes/tests/Fixtures/SourceHostApp/config/modes/devtools-console.php`
  - [ ] explicit source-host test mode
  - [ ] requires `core.foundation|core.kernel|platform.cli|devtools.cli-spikes`
  - [ ] does not modify framework default modes
  - [ ] `name = devtools-console`
  - [ ] fixture `config/app.php` selects it for the `console` target

#### Modifies

- [ ] `docs/architecture/PACKAGING.md`
  - [ ] split runtime packages into two canonical config-ownership states:
    1. config-owning runtime package:
      - [ ] declares `defaultsConfigPath`
      - [ ] exact value is `config/<slug>.php`
      - [ ] ships `config/<slug>.php`
      - [ ] ships `config/rules.php`
    2. configless runtime package:
      - [ ] omits `defaultsConfigPath`
      - [ ] ships no `config/<slug>.php`
      - [ ] ships no `config/rules.php`
      - [ ] does not introduce an empty placeholder config directory
      - [ ] remains a normal runtime module with `moduleId`, `moduleClass`, and `providers`
  - [ ] absence of config ownership MUST NOT weaken:
    - [ ] module metadata validation
    - [ ] provider validation
    - [ ] package scaffold/legal/README validation
    - [ ] dependency-boundary validation
  - [ ] package-specific exceptions are forbidden
  - [ ] configless runtime policy applies generically to any runtime package

- [ ] `framework/tools/gates/package_compliance_gate.php`
  - [ ] preserve all existing package identity, scaffold, legal, README, namespace, and Composer validation
  - [ ] preserve exact runtime module id rule:
    - [ ] `moduleId = <layer>.<slug>`
  - [ ] runtime package validation branches by config ownership:
    - [ ] when `defaultsConfigPath` is present:
      - [ ] it MUST equal `config/<slug>.php`
      - [ ] `config/` MUST exist
      - [ ] `config/<slug>.php` MUST exist
      - [ ] `config/rules.php` MUST exist
      - [ ] existing defaults/rules validation remains unchanged
    - [ ] when `defaultsConfigPath` is absent:
      - [ ] `config/<slug>.php` MUST be absent
      - [ ] `config/rules.php` MUST be absent
      - [ ] an empty placeholder config directory MUST NOT be required
      - [ ] module/provider metadata remains fully validated
  - [ ] partial states fail deterministically:
    - [ ] omitted metadata with config files present
    - [ ] metadata present with missing defaults file
    - [ ] metadata present with missing rules file
    - [ ] metadata value different from `config/<slug>.php`
  - [ ] MUST NOT add a package-id allowlist special case
  - [ ] MUST NOT skip normal compliance checks for configless runtime packages

- [ ] `framework/packages/devtools/cli-spikes/composer.json`
  - [ ] preserve Composer `type = library`
  - [ ] description identifies dev-only command module
  - [ ] require exactly the production source dependencies actually imported:
    - [ ] `php: ^8.4`
    - [ ] `coretsia/core-contracts: ^0.5.0`
    - [ ] `coretsia/core-foundation: ^0.5.0`
  - [ ] remove `coretsia/platform-cli` requirement
  - [ ] preserve PSR-4 namespace
  - [ ] `extra.coretsia` exact runtime metadata:
    - [ ] `kind = runtime`
    - [ ] `moduleId = devtools.cli-spikes`
    - [ ] `moduleClass = Coretsia\Devtools\CliSpikes\Module\CliSpikesModule`
    - [ ] `providers = [Coretsia\Devtools\CliSpikes\Provider\CliSpikesServiceProvider]`
    - [ ] `requires = [core.foundation, platform.cli]`
    - [ ] `conflicts = []`
  - [ ] configless runtime package policy:
    - [ ] omit `defaultsConfigPath`
    - [ ] do not ship `config/cli-spikes.php`
    - [ ] do not ship `config/rules.php`
    - [ ] do not retain an empty config directory
    - [ ] package remains fully compliant as a configless runtime module

- [ ] `framework/packages/devtools/cli-spikes/src/Command/SpikeFingerprintCommand.php`
  - [ ] add exact six public constants
  - [ ] return `self::NAME`
  - [ ] remove platform/cli imports
  - [ ] remove generic Throwable downgrade

- [ ] `framework/packages/devtools/cli-spikes/src/Command/SpikeConfigDebugCommand.php`
  - [ ] add exact six public constants
  - [ ] return `self::NAME`
  - [ ] remove platform/cli imports
  - [ ] preserve key/scenario domain validation
  - [ ] remove generic Throwable downgrade

- [ ] `framework/packages/devtools/cli-spikes/src/Command/DeptracGraphCommand.php`
  - [ ] expose exact six public constants
  - [ ] remove command-local `json|help|h` options and help rendering
  - [ ] preserve owner path validation and tools-only dispatch
  - [ ] remove platform/cli imports
  - [ ] remove generic Throwable downgrade

- [ ] all remaining command adapters
  - [ ] use only contracts-level input/output APIs
  - [ ] preserve tools-only dispatch
  - [ ] contain no platform/cli imports
  - [ ] contain no stdout/stderr writes
  - [ ] return only `0|1`

- [ ] `framework/packages/devtools/cli-spikes/README.md`
  - [ ] remove Phase 0 terminology
  - [ ] remove config-based registry documentation
  - [ ] document dev-only installation versus runtime module metadata distinction
  - [ ] document explicit mode-preset enablement
  - [ ] document exact final command surface
  - [ ] document exact metadata/tag contribution
  - [ ] document no platform/cli concrete dependency
  - [ ] document tools-only dispatch and output policy

- [ ] `docs/adr/INDEX.md`
  - [ ] register ADR-XXXX-devtools-cli-spikes-module-enablement.md

#### Deletes

- [ ] `framework/packages/devtools/cli-spikes/config/cli.php`
  - [ ] package introduces no CLI config defaults
  - [ ] no empty compatibility file remains

- [ ] `framework/packages/devtools/cli-spikes/src/Command/DoctorCommand.php`
  - [ ] replaced by `SpikeDoctorCommand`

- [ ] `framework/packages/devtools/cli-spikes/src/Command/WorkspaceSyncDryRunCommand.php`
- [ ] `framework/packages/devtools/cli-spikes/src/Command/WorkspaceSyncApplyCommand.php`

No class aliases or wrapper commands remain for deleted classes.

### Provider and tag metadata contract (MUST)

For every command class `C`, provider metadata is exactly:

```php
[
    'name' => C::NAME,
    'summary' => C::SUMMARY,
    'group' => C::GROUP,
    'hidden' => C::HIDDEN,
    'arguments' => C::ARGUMENTS,
    'options' => C::OPTIONS,
]
```

Rules:
- [ ] no `aliases` key;
- [ ] no `mode` metadata key;
- [ ] no metadata `priority` key;
- [ ] no optional metadata schema;
- [ ] no unknown keys;
- [ ] no runtime default values in metadata;
- [ ] no paths as option defaults in metadata;
- [ ] no command signature strings;
- [ ] no duplicate command names;
- [ ] no reserved platform command collision;
- [ ] no command service construction during catalog creation.

### Tools-only dispatch boundary (MUST)

Commands remain thin adapters.

The provider and command catalog expose static metadata only.

Availability of the tools-only implementation is an execution-time concern for the selected command, not a provider-registration or catalog-construction concern.

They MAY call only package-owned support under:

```text
Coretsia\Devtools\CliSpikes\Spikes\*
```

That support may load tools-only spike implementation under:

```text
framework/tools/spikes/**
```

Command adapters MUST NOT copy tools algorithms into the package.

The migration MUST NOT move tools/spikes implementation into runtime package source.

Commands MUST NOT:
- [ ] call Kernel Ops;
- [ ] call Kernel runtime services directly;
- [ ] resolve modules or providers;
- [ ] inspect generated artifacts;
- [ ] access the platform command catalog;
- [ ] format final output;
- [ ] read `cli.*` config;
- [ ] write stdout or stderr.

### Cross-cutting (MUST)

#### Output and redaction

- [ ] Every user-visible output call uses `OutputInterface`.
- [ ] Command adapters and package-owned tools bridges MUST produce safe-by-construction json-like records.
- [ ] The package performs no final formatting and no package-local redaction.
- [ ] The package MUST follow `docs/ssot/sensitive-data-redaction.md`.
- [ ] The package MUST NOT define or maintain:
  - [ ] a sensitive-key classifier
  - [ ] a sensitive-value classifier
  - [ ] a redaction policy
  - [ ] a redaction hasher
  - [ ] a pattern registry
  - [ ] a second redaction vocabulary
- [ ] Commands and tools-only adapters MUST NOT intentionally pass the following to `OutputInterface`:
  - [ ] raw argv
  - [ ] raw config or env values
  - [ ] credentials or tokens
  - [ ] cookies or authorization headers
  - [ ] raw SQL
  - [ ] payloads
  - [ ] absolute paths
  - [ ] Throwable messages or stack traces
- [ ] Where omission or a fixed safe reason token is sufficient, owner output MUST omit the value rather than rely on late redaction.
- [ ] Final defense-in-depth redaction remains exclusively owned by platform/cli through `SensitiveDataRedactorInterface`.
- [ ] Devtools command classes and package services MUST NOT receive or resolve `SensitiveDataRedactorInterface` in this epic.
- [ ] Devtools command classes and package services MUST NOT import or instantiate the concrete platform redactor.
- [ ] Command output remains deterministic and recursively json-like.

#### Context, UoW, and reset

- [ ] All 2.30.0 normal-command Context and UoW rules apply unchanged.
- [ ] Every devtools command is a normal command.
- [ ] The platform `CommandRunner` supplies exactly one CLI UoW per command invocation.
- [ ] Commands MUST NOT receive or resolve:
  - [ ] `KernelRuntimeInterface`
  - [ ] `ContextAccessorInterface`
  - [ ] `ContextKeys`
  - [ ] `ContextStore`
  - [ ] `ResetOrchestrator`
- [ ] Commands MUST NOT:
  - [ ] create or nest UoWs
  - [ ] read or write context values
  - [ ] create correlation ids
  - [ ] create UoW ids
  - [ ] invoke hooks
  - [ ] invoke reset orchestration
  - [ ] enumerate reset tags
- [ ] Command domain behavior and output MUST NOT depend on correlation id, UoW id, or UoW type.
- [ ] Platform-owned observability MAY use canonical context values according to 2.30.0.
- [ ] Command services are stateless and require no `ResetInterface`.

#### Observability

- [ ] All 2.30.0 normal-command observability rules apply unchanged.
- [ ] The package introduces no metric, span, log-event, or lifecycle-observability ownership.
- [ ] Commands and package services MUST NOT receive:
  - [ ] `TracerPortInterface`
  - [ ] `MeterPortInterface`
  - [ ] `LoggerInterface`
  - [ ] `Stopwatch`
- [ ] Platform `CommandRunner` remains the sole owner of:
  - [ ] span `cli.command`
  - [ ] metric `cli.command_total`
  - [ ] metric `cli.command_duration_ms`
  - [ ] safe command completion/failure logs
- [ ] The canonical `operation` value is the selected command name.
- [ ] The package MUST NOT emit duplicate command lifecycle observability.
- [ ] Tools-only code MAY return deterministic diagnostic data through the command adapter.
- [ ] Tools-only code MUST NOT emit platform CLI lifecycle signals.
- [ ] Observability failures remain isolated by platform/cli and MUST NOT affect devtools command exit semantics.

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesProviderSourceDefinitionsParityTest.php`
  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesProductionSourceHasNoPlatformCliImportsTest.php`

  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesDefinesNoRedactionModelContractTest.php`
    - [ ] production source defines no redaction engine or policy
    - [ ] production source defines no sensitive-key or sensitive-value classifier
    - [ ] production source defines no redaction hasher or pattern registry
    - [ ] command and support classes do not receive `SensitiveDataRedactorInterface`
    - [ ] no concrete `Coretsia\Redaction\*` class is imported
    - [ ] no second runtime redaction vocabulary exists

  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesComposerModuleMetadataTest.php`
    - [ ] Composer type remains library
    - [ ] Coretsia kind is runtime
    - [ ] exact module id/provider/requires
    - [ ] no platform-cli Composer requirement
    - [ ] no defaultsConfigPath

  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesCommandMetadataConstantsTest.php`
    - [ ] exact six constants on all five commands
    - [ ] exact command names and descriptor shapes
    - [ ] `name() === NAME`
    - [ ] every base descriptor has `GROUP = 'devtools'`
    - [ ] group equality is verified independently of command-name namespaces

  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesServiceProviderCliCommandTaggingTest.php`
    - [ ] exact service ids
    - [ ] exact six metadata keys
    - [ ] metadata equals constants
    - [ ] actual priority `0`

  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesDoesNotUseLegacyCommandRegistryTest.php`
    - [ ] config/cli.php absent
    - [ ] no `cli.commands` text in production source/config

  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesHasNoConfigEnablementToggleTest.php`
    - [ ] no `cli.spikes.enabled`
    - [ ] no `cli.devtools.enabled`
    - [ ] no Bootstrap app.php-specific devtools key
    - [ ] activation remains exclusively preset/module based

  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesHasNoReservedDoctorCollisionTest.php`
    - [ ] no tagged command named `doctor`
    - [ ] `spike:doctor` exists

  - [ ] `framework/packages/devtools/cli-spikes/tests/Contract/WorkspaceSyncUsesOneCanonicalCommandClassTest.php`
    - [ ] old classes absent
    - [ ] one service id/name
    - [ ] exact mutually exclusive mode options

- [ ] existing `CommandsDoNotWriteToStdoutTest.php`
  - [ ] remains green against all final commands

- [ ] existing thin-adapter contract tests
  - [ ] updated for renamed/consolidated commands
  - [ ] continue proving dispatch only through SpikesBootstrap/tools workflows

- Integration:
  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/CommandsAreAbsentWhenModuleNotEnabledTest.php`
  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/SpikeDoctorDispatchesToToolsRuntimeTest.php`
  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/SpikeFingerprintDispatchesToToolsRuntimeTest.php`
  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/SpikeConfigDebugDispatchesToToolsRuntimeTest.php`
  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/DeptracGraphDispatchesToToolsRuntimeTest.php`
  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/WorkspaceSyncDryRunDispatchesToToolsRuntimeTest.php`
  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/WorkspaceSyncApplyDispatchesToToolsRuntimeTest.php`

  - [ ] `framework/tools/tests/Integration/PackageComplianceGateConfiglessRuntimePolicyTest.php`
    - [ ] configured runtime package passes
    - [ ] configless runtime package passes
    - [ ] configless runtime package still requires:
      - [ ] canonical module class
      - [ ] canonical provider
      - [ ] exact module id
      - [ ] complete baseline scaffold
    - [ ] configless runtime package with `config/<slug>.php` fails
    - [ ] configless runtime package with `config/rules.php` fails
    - [ ] declared `defaultsConfigPath` without files fails
    - [ ] non-canonical module id such as `devtools.cli_spikes` fails
    - [ ] diagnostics remain deterministic, sorted, and relative

  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/ConsolePresetEnablesCliSpikesModuleTest.php`
    - [ ] fixture `config/app.php` selects exact preset `devtools-console` for app target `console`
    - [ ] fixture `config/modes/devtools-console.php` is loaded
    - [ ] the custom preset requires `devtools.cli-spikes`
    - [ ] all five commands become discoverable

  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/ProductionConsolePresetDoesNotExposeCliSpikesCommandsTest.php`
    - [ ] the selected console preset does not contain `devtools.cli-spikes`
    - [ ] no devtools command descriptor is present
    - [ ] platform CLI remains otherwise functional
    - [ ] the package may be installed, but absence from the selected preset keeps the module disabled

  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/CommandsAreDiscoverableThroughFinalCommandCatalogTest.php`
    - [ ] enables explicit fixture mode containing `devtools.cli-spikes`
    - [ ] boots canonical source operations host
    - [ ] discovers all five commands through `ReservedTags::CLI_COMMAND`
    - [ ] no platform/cli production-source special case

  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/CliSpikesCommandsResolveLazilyTest.php`
    - [ ] list/help do not instantiate command services

  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/UnexpectedThrowableUsesCanonicalCliErrorBoundaryTest.php`
    - [ ] command adapter does not downgrade the Throwable locally

  - [ ] `framework/packages/devtools/cli-spikes/tests/Integration/CliSpikesOutputUsesCanonicalCliRedactionBoundaryTest.php`
    - [ ] uses a test-only command/tool fixture containing fake sensitive values
    - [ ] proves the final composed platform/cli pipeline invokes the shared redaction port
    - [ ] proves raw fixture values do not reach final stdout or stderr
    - [ ] proves no package-local redactor is resolved or invoked
    - [ ] proves final redaction occurs once

- Architecture:
  - [ ] deptrac proves `devtools/cli-spikes` production source depends only on allowed layers.
  - [ ] package compliance accepts the explicit dev-only runtime-module model.
  - [ ] no default framework mode references `devtools.cli-spikes`.
  - [ ] repo-root `skeleton/config/modes/*.php` remains absent.
  - [ ] repo-root `skeleton/config/modules.php` remains absent.
  - [ ] `skeleton/apps/*/config/modules.php` remains absent.
  - [ ] no `no_skeleton_*` gate contains a CLI-spikes exception.
  - [ ] package compliance accepts the package through generic configless-runtime policy, not allowlisting.
  - [ ] canonical module id is exactly `devtools.cli-spikes`.

### DoD (MUST)

- [ ] The package is installed/deployed as dev-only policy but is Coretsia module-plan visible when explicitly enabled.
- [ ] Composer package type remains `library`.
- [ ] `extra.coretsia.kind` is `runtime` with module id `devtools.cli-spikes`.
- [ ] The canonical module id is `devtools.cli-spikes`; underscore form `devtools.cli_spikes` is absent.
- [ ] The package is a canonical configless runtime package.
- [ ] `defaultsConfigPath` is absent.
- [ ] No package config defaults or config rules are shipped.
- [ ] Generic package-compliance policy accepts configless runtime modules.
- [ ] No package-compliance allowlist entry is introduced.
- [ ] No framework-default mode enables the module.
- [ ] No `cli.commands` registry file or fallback remains.
- [ ] No platform/cli concrete import or Composer requirement remains.
- [ ] Foundation `ReservedTags::CLI_COMMAND` is used directly.
- [ ] No package-local ReservedTags mirror exists.
- [ ] Provider is source-host-capable and dual-interface.
- [ ] Source and definition contributions are semantically identical.
- [ ] Exactly five commands are tagged and discoverable when the module is enabled.
- [ ] Every tag uses exact six-key metadata and actual priority `0`.
- [ ] No `aliases`, `mode`, metadata `priority`, or unknown key exists.
- [ ] No command name contains spaces or option syntax.
- [ ] No command collides with platform-owned `doctor|help|list` behavior.
- [ ] One `workspace:sync` command owns both dry-run and apply modes.
- [ ] Command-local format/help options removed where platform CLI owns those concerns.
- [ ] Commands remain thin tools-only adapters.
- [ ] Expected deterministic tool failures are mapped safely.
- [ ] Unexpected Throwables propagate to the canonical CLI error boundary.
- [ ] User-visible output uses only `OutputInterface`.
- [ ] The package follows `docs/ssot/sensitive-data-redaction.md`.
- [ ] Devtools output is safe by construction.
- [ ] No package-local or tools-only runtime redaction model exists.
- [ ] No command or package service receives or resolves a redactor.
- [ ] Final defense-in-depth redaction remains owned exclusively by platform/cli.
- [ ] All unit, contract, integration, architecture, ECS, PHPStan, and package-compliance checks pass.
- [ ] Package installation alone never enables `devtools.cli-spikes`.
- [ ] The exact `devtools-console` fixture preset enables the module.
- [ ] A preset without `devtools.cli-spikes` exposes no devtools commands even when the package is installed.
- [ ] No `appEnv`, `debug`, CLI config, or package-presence auto-activation exists.
- [ ] ADR-XXXX-devtools-cli-spikes-module-enablement.md records the final activation decision.
- [ ] Repo-root default skeleton contains no CLI-spikes mode preset.
- [ ] Repo-root default skeleton contains no CLI-spikes module-selection file.
- [ ] The `devtools-console` preset exists only in the package-local synthetic fixture or in project-owned post-creation configuration.
- [ ] `no_skeleton_mode_presets_default_gate.php` remains unchanged.
- [ ] `no_skeleton_modules_default_gate.php` remains unchanged.

---

### 2.70.0 CLI Performance Gate (MUST) [TOOLING]

---
type: tools
phase: 2
epic_id: "2.70.0"
owner_path: "framework/tools/gates/"

goal: "Benchmark execution time of key CLI commands on a pinned benchmark runner and fail only there if performance degrades beyond threshold."
provides:
- "Deterministic performance benchmarking of CLI commands"
- "Baseline timings stored in SSoT"
- "CI gate that compares current timings against baseline"

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
  - 0.130.0 — CLI base exists
  - 0.140.0 — cli-spikes commands exist (for testing)
  - 1.50.0 — tooling baseline exists

- Required deliverables:
  - `coretsia` CLI executable.

#### Compile-time deps

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer performance:gate` — runs performance benchmarks
- CI:
  - run only in a dedicated pinned benchmark job
  - MUST NOT gate generic shared-runner CI, because timings there are not deterministic

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/config/performance.php` — tooling-local performance benchmark config:
  - [ ] list of commands to benchmark (e.g., `coretsia list`, `coretsia help`, `coretsia spike:fingerprint`)
  - [ ] threshold multiplier (e.g., 1.2 = 20% slower allowed)
  - [ ] baseline file path `framework/tools/config/performance.baseline.json`
  - [ ] baseline MUST be tied to the pinned benchmark environment / runner class

- [ ] `framework/tools/gates/performance_gate.php` — deterministic gate:
  - [ ] runs each command multiple times (e.g., 3) and takes median execution time
  - [ ] benchmark cases are declared as:
    - [ ] one canonical safe benchmark id
    - [ ] one fixed ordered token list used only for process execution
  - [ ] diagnostics identify a benchmark only by its canonical safe benchmark id
  - [ ] diagnostics MUST NOT reconstruct, join, quote, or print the raw command line
  - [ ] child stdout and stderr are captured and discarded
  - [ ] child stdout and stderr MUST NOT be inherited by the gate process
  - [ ] child stdout and stderr MUST NOT be copied into diagnostics
  - [ ] command execution MUST NOT use shell interpolation
  - [ ] benchmark child environment is an explicit bounded allowlist
  - [ ] inherited environment values MUST NOT be rendered or copied into diagnostics
  - [ ] compares against baseline (if exists) or creates baseline if not
  - [ ] if any command exceeds baseline * threshold, prints `CORETSIA_PERFORMANCE_DEGRADED` + details
  - [ ] uses `ConsoleOutput`
  - [ ] supports `--update-baseline` flag to update baseline after intentional improvements
  - [ ] MUST resolve the tools root deterministically from the executing gate file.
  - [ ] MUST load `framework/tools/spikes/_support/bootstrap.php` before scanning.
  - [ ] If bootstrap is missing or unreadable:
    - [ ] MUST attempt to load `framework/tools/spikes/_support/ConsoleOutput.php`
    - [ ] MUST print the gate scan-failed code using `ConsoleOutput::codeWithDiagnostics($code, [])`
    - [ ] MUST exit with code `1`
  - [ ] MUST use `Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics()` for all non-empty diagnostics output.
  - [ ] MUST NOT use `echo`, `print`, `var_dump`, `print_r`, `printf`, direct `STDOUT`, or direct `STDERR` for diagnostics.
  - [ ] MUST load `framework/tools/spikes/_support/ErrorCodes.php` when available.
  - [ ] MUST resolve error code constants from `ErrorCodes` when defined.
  - [ ] MUST keep deterministic fallback string codes when `ErrorCodes` is unavailable.
  - [ ] MUST use two code classes when applicable:
    - [ ] violation/finding code
    - [ ] scan-failed/tooling-failed code
  - [ ] MUST suppress warnings/notices around filesystem probing where existing gates do so, to avoid output pollution.
  - [ ] MUST wrap scanning/parsing logic in `try/catch`.
  - [ ] On unexpected throwable:
    - [ ] MUST emit the scan-failed code through `ConsoleOutput::codeWithDiagnostics($code, [])`
    - [ ] MUST exit with code `1`
  - [ ] On pass:
    - [ ] MUST emit no output
    - [ ] MUST exit with code `0`
  - [ ] On violation/finding:
    - [ ] MUST emit only the deterministic violation/finding code and sorted diagnostics
    - [ ] MUST exit with code `1`
  - [ ] Diagnostics MUST be:
    - [ ] deduplicated
    - [ ] sorted by byte-order `strcmp`
    - [ ] stable across OS/filesystem order/locale
    - [ ] free of raw argv, reconstructed command lines, child stdout/stderr, env values, absolute paths, raw payloads, source snippets, secrets, tokens, credentials, stack traces, and exception messages.

- [ ] `framework/tools/config/performance.baseline.json` — initial baseline (committed)

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `performance:gate` → `@composer --no-interaction --working-dir=framework run-script performance:gate --`

- [ ] `framework/composer.json` — add gate script
  - [ ] `performance:gate` → `@php tools/gates/performance_gate.php`
  - [ ] add to `gates`

- [ ] `.github/workflows/performance-benchmark.yml` — dedicated pinned-runner workflow/job for the performance gate

- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_PERFORMANCE_DEGRADED`
  - [ ] `CORETSIA_PERFORMANCE_GATE_SCAN_FAILED`

- [ ] add command `performance:gate` in `docs/guides/commands.md`
- [ ] update command `composer gates` in `docs/guides/commands.md`

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

- [ ] Integration test: mock slow command, run gate, assert failure.

### Tests

- [ ] `framework/tools/tests/Integration/PerformanceGateTest.php`
  - [ ] executes a deterministic fake slow command
  - [ ] asserts the degradation code and canonical benchmark id
  - [ ] asserts stable diagnostic ordering
  - [ ] asserts the expected non-zero exit code

- [ ] `framework/tools/tests/Integration/PerformanceGateDoesNotLeakChildProcessDataTest.php`
  - [ ] child command writes fake secrets, env-like values, paths, payloads, and argv-like text to stdout and stderr
  - [ ] gate captures and discards both streams
  - [ ] none of the fake values appears in gate diagnostics
  - [ ] raw command tokens are not joined or rendered
  - [ ] only code, canonical benchmark id, timings, and threshold are emitted

### DoD

- [ ] Gate implemented, baseline created, CI integrated.
- [ ] Diagnostics expose only deterministic code, canonical benchmark id, timings, and threshold.
- [ ] Diagnostics never expose raw argv, reconstructed command lines, child output, env values, absolute paths, payloads, or secrets.
- [ ] The gate remains safe by construction and requires no runtime redaction service.
