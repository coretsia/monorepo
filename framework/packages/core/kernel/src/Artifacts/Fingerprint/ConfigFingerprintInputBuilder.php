<?php

declare(strict_types=1);

/*
 * Coretsia Framework (Monorepo)
 *
 * Project: Coretsia Framework (Monorepo)
 * Authors: Vladyslav Mudrichenko and contributors
 * Copyright (c) 2026 Vladyslav Mudrichenko
 *
 * SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
 * SPDX-License-Identifier: Apache-2.0
 *
 * For contributors list, see git history.
 * See LICENSE and NOTICE in the project root for full license information.
 */

namespace Coretsia\Kernel\Artifacts\Fingerprint;

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Module\ModulePlan;

/**
 * Builds deterministic safe fingerprint input for compiled Kernel artifacts.
 *
 * This builder is intentionally a projection/hashing layer only. It consumes
 * already resolved and already compiled inputs:
 *
 * - resolved BootstrapConfig;
 * - resolved ModulePlan;
 * - ConfigKernel::compile(...) result;
 * - the same explicit source candidate arrays passed to ConfigKernel::compile(...);
 * - EnvRepositoryInterface source metadata needed for env-overlay provenance;
 * - kernel config subtree for canonical dotenv file templates.
 *
 * It MUST NOT:
 *
 * - resolve BootstrapConfig;
 * - re-run ModulePlan resolution;
 * - re-run preset resolution;
 * - re-run config discovery;
 * - re-run env loading;
 * - re-run config merging;
 * - scan package directories;
 * - scan app targets;
 * - scan unknown config files;
 * - enumerate arbitrary dotenv files;
 * - read process env directly;
 * - emit spans, metrics, logs, stdout, or stderr.
 *
 * Raw config values and raw env values are never emitted into the fingerprint
 * input. Value influence is represented by deterministic hash/len/type
 * metadata only.
 *
 * @internal
 */
final readonly class ConfigFingerprintInputBuilder
{
    public const int SCHEMA_VERSION = 1;

    private const string KEY_CONFIG = 'config';
    private const string KEY_SOURCES = 'sources';
    private const string KEY_OWNERS = 'owners';
    private const string KEY_VALIDATION = 'validation';
    private const string KEY_VALIDATION_SUBJECTS = 'validationSubjects';

    private const string KERNEL_KEY_ENV = 'env';
    private const string KERNEL_KEY_DOTENV = 'dotenv';
    private const string KERNEL_KEY_FILES = 'files';
    private const string KERNEL_KEY_FINGERPRINT = 'fingerprint';
    private const string KERNEL_KEY_SKELETON_IGNORE_PREFIXES = 'skeleton_ignore_prefixes';

    private const string ENV_TEMPLATE = '<env>';

    private const string KEY_ENV_OVERLAY_MAPPINGS = 'envOverlayMappings';
    private const string KEY_CONFIG_SOURCE_FILES = 'configSourceFiles';

    private const string SOURCE_KIND_SKELETON_CONFIG = 'skeleton_config';

    private const string SOURCE_KIND_PACKAGE_CONFIG = 'package_config';
    private const string SOURCE_KIND_PACKAGE_RULES = 'package_rules';
    private const string SOURCE_KIND_EXPLICIT_RULES = 'explicit_rules';
    private const string SOURCE_KIND_MODE_PRESET = 'mode_preset';
    private const string SOURCE_KIND_DOTENV = 'dotenv';

    private const string EXISTS_TRUE = 'true';
    private const string EXISTS_FALSE = 'false';

    private const int MAX_SAFE_STRING_BYTES = 256;

    private const string SAFE_TOKEN_PATTERN = '/\A[A-Za-z0-9_.\/-]{1,256}\z/';
    private const string SAFE_KEY_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63}|\[[0-9]{1,9}])*\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string WINDOWS_ABSOLUTE_PATH_PATTERN = '/\A[A-Za-z]:[\/\\\\]/';
    private const string SENSITIVE_KEY_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|dsn|sql|raw|payload|stacktrace|trace|email|phone|username|fullname|userid|tenantid)(?![A-Za-z0-9])/i';

    public function __construct(
        private PayloadNormalizer $payloadNormalizer = new PayloadNormalizer(),
        private DeterministicFileLister $fileLister = new DeterministicFileLister(),
    ) {
    }

    /**
     * Builds deterministic safe fingerprint input.
     *
     * `$compiledConfig` must be the exact result returned by
     * ConfigKernel::compile(...). This method intentionally does not call
     * ConfigKernel and does not derive replacement inputs.
     *
     * @param array{
     *     config: array<string,mixed>,
     *     sources: list<ConfigValueSource>,
     *     owners: array<string, array<string, null|bool|int|string>>,
     *     envOverlayMappings: list<array{
     *         env: non-empty-string,
     *         kind: non-empty-string,
     *         path: non-empty-string,
     *         root: non-empty-string,
     *         sourceId: non-empty-string,
     *         type: non-empty-string,
     *         precedence?: int,
     *         allowedValues?: list<null|bool|int|string>
     *     }>,
     *     configSourceFiles: list<array{
     *         exists: bool,
     *         hash?: non-empty-string,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         len?: int,
     *         path: non-empty-string,
     *         readable: bool,
     *         root?: non-empty-string,
     *         sourceId: non-empty-string
     *     }>,
     *     validation: ConfigValidationResult,
     *     validationSubjects: array{
     *         unvalidated: list<array{root: non-empty-string, ownership: string, validation: string}>,
     *         validated: list<array{root: non-empty-string, ownership: string, validation: string}>
     *     }
     * } $compiledConfig
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $packageDefaultSources
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $packageRuleSources
     * @param list<non-empty-string> $splitRoots
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId?: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $explicitRuleSources
     * @param list<array{
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int|null
     * }> $modePresetSourceCandidates
     *
     * @return array<string, mixed>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function build(
        BootstrapConfig $bootstrapConfig,
        ModulePlan $modulePlan,
        EnvRepositoryInterface $env,
        array $kernelConfig,
        array $compiledConfig,
        array $packageDefaultSources,
        array $packageRuleSources,
        array $splitRoots = [],
        array $explicitRuleSources = [],
        array $modePresetSourceCandidates = [],
    ): array {
        self::assertCompiledConfigShape($compiledConfig);

        $skeletonRoot = self::normalizeSkeletonRoot($bootstrapConfig->skeletonRoot());
        $skeletonIgnorePrefixes = self::skeletonIgnorePrefixes($kernelConfig);

        $skeletonConfigSources = self::compiledSourceFileList(
            $compiledConfig[self::KEY_CONFIG_SOURCE_FILES],
        );

        $packageConfigCandidates = $this->sourceCandidateList(
            kind: self::SOURCE_KIND_PACKAGE_CONFIG,
            candidates: $packageDefaultSources,
            skeletonRoot: $skeletonRoot,
            skeletonIgnorePrefixes: $skeletonIgnorePrefixes,
        );

        $packageRuleCandidates = $this->sourceCandidateList(
            kind: self::SOURCE_KIND_PACKAGE_RULES,
            candidates: $packageRuleSources,
            skeletonRoot: $skeletonRoot,
            skeletonIgnorePrefixes: $skeletonIgnorePrefixes,
        );

        $explicitRuleCandidates = $this->sourceCandidateList(
            kind: self::SOURCE_KIND_EXPLICIT_RULES,
            candidates: $explicitRuleSources,
            skeletonRoot: $skeletonRoot,
            skeletonIgnorePrefixes: $skeletonIgnorePrefixes,
        );

        $modePresetCandidates = $this->sourceCandidateList(
            kind: self::SOURCE_KIND_MODE_PRESET,
            candidates: $modePresetSourceCandidates,
            skeletonRoot: $skeletonRoot,
            skeletonIgnorePrefixes: $skeletonIgnorePrefixes,
        );

        $dotenvCandidates = $this->dotenvCandidateList(
            bootstrapConfig: $bootstrapConfig,
            kernelConfig: $kernelConfig,
        );

        $input = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'bootstrap' => self::bootstrapIdentity($bootstrapConfig),
            'fingerprintPolicy' => [
                'skeletonIgnorePrefixes' => $skeletonIgnorePrefixes,
            ],
            'modulePlan' => self::modulePlanIdentity($modulePlan),
            'compiledConfig' => [
                'roots' => self::compiledConfigRoots($compiledConfig[self::KEY_CONFIG]),
                'valueFingerprints' => $this->compiledConfigValueFingerprints($compiledConfig[self::KEY_CONFIG]),
                'sources' => self::sourceList($compiledConfig[self::KEY_SOURCES]),
                'owners' => self::ownerMap($compiledConfig[self::KEY_OWNERS]),
                'validation' => self::validationSummary($compiledConfig[self::KEY_VALIDATION]),
                'validationSubjects' => self::validationSubjects($compiledConfig[self::KEY_VALIDATION_SUBJECTS]),
            ],
            'sourceCandidates' => [
                self::SOURCE_KIND_SKELETON_CONFIG => $skeletonConfigSources,
                self::SOURCE_KIND_PACKAGE_CONFIG => $packageConfigCandidates,
                self::SOURCE_KIND_PACKAGE_RULES => $packageRuleCandidates,
                self::SOURCE_KIND_EXPLICIT_RULES => $explicitRuleCandidates,
                self::SOURCE_KIND_MODE_PRESET => $modePresetCandidates,
            ],
            'splitRoots' => self::splitRoots($splitRoots),
            'dotenvCandidates' => $dotenvCandidates,
            'envOverlay' => [
                'mappings' => self::envOverlayMappings($compiledConfig[self::KEY_ENV_OVERLAY_MAPPINGS]),
                'sources' => self::envOverlaySourceMetadata($env, $compiledConfig[self::KEY_ENV_OVERLAY_MAPPINGS]),
            ],
            'observabilityMetadata' => [
                'bucketNames' => [
                    'schemaVersion',
                    'bootstrap',
                    'fingerprintPolicy',
                    'modulePlan',
                    'compiledConfig',
                    'sourceCandidates',
                    'splitRoots',
                    'dotenvCandidates',
                    'envOverlay',
                ],
                'sourceCandidateCounts' => [
                    self::SOURCE_KIND_SKELETON_CONFIG => \count($skeletonConfigSources),
                    self::SOURCE_KIND_PACKAGE_CONFIG => \count($packageConfigCandidates),
                    self::SOURCE_KIND_PACKAGE_RULES => \count($packageRuleCandidates),
                    self::SOURCE_KIND_EXPLICIT_RULES => \count($explicitRuleCandidates),
                    self::SOURCE_KIND_MODE_PRESET => \count($modePresetCandidates),
                    self::SOURCE_KIND_DOTENV => \count($dotenvCandidates),
                ],
                'missingCandidateCounts' => [
                    self::SOURCE_KIND_SKELETON_CONFIG => self::missingCandidateCount($skeletonConfigSources),
                    self::SOURCE_KIND_PACKAGE_CONFIG => self::missingCandidateCount($packageConfigCandidates),
                    self::SOURCE_KIND_PACKAGE_RULES => self::missingCandidateCount($packageRuleCandidates),
                    self::SOURCE_KIND_EXPLICIT_RULES => self::missingCandidateCount($explicitRuleCandidates),
                    self::SOURCE_KIND_MODE_PRESET => self::missingCandidateCount($modePresetCandidates),
                    self::SOURCE_KIND_DOTENV => self::missingCandidateCount($dotenvCandidates),
                ],
                'validationSubjectCounts' => [
                    'validated' => \count($compiledConfig[self::KEY_VALIDATION_SUBJECTS]['validated']),
                    'unvalidated' => \count($compiledConfig[self::KEY_VALIDATION_SUBJECTS]['unvalidated']),
                ],
            ],
        ];

        return $this->payloadNormalizer->normalizeMap($input, 'fingerprintInput');
    }

    /**
     * @return array{
     *     appTarget: string,
     *     appEnv: string,
     *     preset: string,
     *     debug: bool,
     *     envSourcePolicy: string
     * }
     */
    private static function bootstrapIdentity(BootstrapConfig $bootstrapConfig): array
    {
        return [
            'appTarget' => $bootstrapConfig->appTarget()->value,
            'appEnv' => $bootstrapConfig->appEnv(),
            'preset' => $bootstrapConfig->preset(),
            'debug' => $bootstrapConfig->debug(),
            'envSourcePolicy' => $bootstrapConfig->envSourcePolicy()->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function modulePlanIdentity(ModulePlan $modulePlan): array
    {
        $payload = $modulePlan->toArray();

        return [
            'schemaVersion' => self::safeInt($payload['schemaVersion'] ?? ModulePlan::SCHEMA_VERSION),
            'hash' => self::hashJsonLike($payload),
            'moduleCount' => self::safeCount($payload['modules'] ?? null),
            'enabledModuleCount' => self::safeCount($payload['enabled'] ?? null),
            'disabledModuleCount' => self::safeCount($payload['disabled'] ?? null),
            'warningCount' => self::safeCount($payload['warnings'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $config
     *
     * @return list<non-empty-string>
     */
    private static function compiledConfigRoots(array $config): array
    {
        $roots = [];

        foreach (\array_keys($config) as $root) {
            if (\is_string($root) && self::isSafeRoot($root)) {
                $roots[] = $root;
            }
        }

        \usort($roots, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $roots;
    }

    /**
     * Produces safe hash/len/type metadata for compiled config paths without
     * embedding raw config values.
     *
     * @param array<string,mixed> $config
     *
     * @return array<string, array{hash: string, len: int, type: string}>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private function compiledConfigValueFingerprints(array $config): array
    {
        $fingerprints = [];

        foreach ($config as $root => $value) {
            if (!\is_string($root) || !self::isSafeRoot($root)) {
                continue;
            }

            $this->collectValueFingerprint(
                out: $fingerprints,
                path: $root,
                value: $value,
            );
        }

        \ksort($fingerprints, \SORT_STRING);

        return $fingerprints;
    }

    /**
     * @param array<string, array{hash: string, len: int, type: string}> $out
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private function collectValueFingerprint(
        array &$out,
        string $path,
        mixed $value,
    ): void {
        $normalized = $this->payloadNormalizer->normalize($value, $path);
        $bytes = self::stableJsonBytes($normalized);

        $out[$path] = [
            'hash' => \hash('sha256', $bytes),
            'len' => \strlen($bytes),
            'type' => self::jsonLikeType($normalized),
        ];

        if (!\is_array($normalized)) {
            return;
        }

        if (\array_is_list($normalized)) {
            foreach ($normalized as $index => $item) {
                $this->collectValueFingerprint(
                    out: $out,
                    path: $path . '[' . $index . ']',
                    value: $item,
                );
            }

            return;
        }

        $keys = \array_keys($normalized);
        \usort($keys, static fn (string $a, string $b): int => \strcmp($a, $b));

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                continue;
            }

            $childPath = self::safeKeyPath($path, $key);

            if ($childPath === null) {
                continue;
            }

            $this->collectValueFingerprint(
                out: $out,
                path: $childPath,
                value: $normalized[$key],
            );
        }
    }

    /**
     * @param list<ConfigValueSource> $sources
     *
     * @return list<array<string, mixed>>
     */
    private static function sourceList(array $sources): array
    {
        $out = [];

        foreach ($sources as $source) {
            if (!$source instanceof ConfigValueSource) {
                continue;
            }

            $out[] = self::sourceMetadata($source);
        }

        \usort(
            $out,
            static function (array $a, array $b): int {
                return \strcmp((string)($a['sourceId'] ?? ''), (string)($b['sourceId'] ?? ''))
                    ?: \strcmp((string)($a['keyPath'] ?? ''), (string)($b['keyPath'] ?? ''))
                        ?: \strcmp((string)($a['type'] ?? ''), (string)($b['type'] ?? ''));
            },
        );

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function sourceMetadata(ConfigValueSource $source): array
    {
        $metadata = [
            'type' => $source->type()->value,
            'root' => self::safeRootOrPlaceholder($source->root()),
            'sourceId' => self::safeSourceIdOrPlaceholder($source->sourceId()),
            'path' => self::safeRelativePathOrNull($source->path()),
            'keyPath' => self::safeKeyPathOrNull($source->keyPath()),
            'directive' => self::safeTokenOrNull($source->directive()),
            'precedence' => $source->precedence(),
            'redacted' => $source->isRedacted(),
            'meta' => self::safeMetaMap($source->meta()),
        ];

        \ksort($metadata, \SORT_STRING);

        return $metadata;
    }

    /**
     * @param array<string, array<string, null|bool|int|string>> $owners
     *
     * @return array<string, array<string, null|bool|int|string>>
     */
    private static function ownerMap(array $owners): array
    {
        $out = [];

        foreach ($owners as $sourceId => $owner) {
            if (!\is_string($sourceId)) {
                continue;
            }

            $safeSourceId = self::safeSourceIdOrPlaceholder($sourceId);
            $out[$safeSourceId] = self::safeOwnerMetadata($owner);
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    /**
     * @param array<string, mixed> $owner
     *
     * @return array<string, null|bool|int|string>
     */
    private static function safeOwnerMetadata(array $owner): array
    {
        $out = [];

        foreach ($owner as $key => $value) {
            if (!\is_string($key) || !self::isSafeSmallKey($key)) {
                continue;
            }

            if ($value === null || \is_bool($value) || \is_int($value)) {
                $out[$key] = $value;

                continue;
            }

            if (\is_string($value)) {
                $safe = self::safeMetadataStringOrNull($value);

                if ($safe !== null) {
                    $out[$key] = $safe;
                }
            }
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    /**
     * @return array{status: string}
     */
    private static function validationSummary(ConfigValidationResult $validation): array
    {
        return [
            'status' => $validation->isFailure() ? 'failure' : 'success',
        ];
    }

    /**
     * @param array{
     *     unvalidated: list<array{root: non-empty-string, ownership: string, validation: string}>,
     *     validated: list<array{root: non-empty-string, ownership: string, validation: string}>
     * } $subjects
     *
     * @return array{
     *     unvalidated: list<array{root: string, ownership: string, validation: string}>,
     *     validated: list<array{root: string, ownership: string, validation: string}>
     * }
     */
    private static function validationSubjects(array $subjects): array
    {
        return [
            'unvalidated' => self::validationSubjectList($subjects['unvalidated'] ?? []),
            'validated' => self::validationSubjectList($subjects['validated'] ?? []),
        ];
    }

    /**
     * @param list<array<string,mixed>> $subjects
     *
     * @return list<array{root: string, ownership: string, validation: string}>
     */
    private static function validationSubjectList(array $subjects): array
    {
        $out = [];

        foreach ($subjects as $subject) {
            if (!\is_array($subject)) {
                continue;
            }

            $root = $subject['root'] ?? null;
            $ownership = $subject['ownership'] ?? null;
            $validation = $subject['validation'] ?? null;

            if (!\is_string($root) || !self::isSafeRoot($root)) {
                continue;
            }

            $out[] = [
                'root' => $root,
                'ownership' => \is_string($ownership) ? self::safeTokenOrPlaceholder($ownership) : 'unknown',
                'validation' => \is_string($validation) ? self::safeTokenOrPlaceholder($validation) : 'unknown',
            ];
        }

        \usort(
            $out,
            static fn (array $a, array $b): int => \strcmp($a['root'], $b['root'])
                ?: \strcmp($a['ownership'], $b['ownership'])
                    ?: \strcmp($a['validation'], $b['validation']),
        );

        return $out;
    }

    /**
     * @param list<array{
     *     exists: bool,
     *     hash?: non-empty-string,
     *     kind: non-empty-string,
     *     layer: non-empty-string,
     *     len?: int,
     *     path: non-empty-string,
     *     readable: bool,
     *     root?: non-empty-string,
     *     sourceId: non-empty-string
     * }> $sourceFiles
     *
     * @return list<array{
     *     exists: string,
     *     hash?: non-empty-string,
     *     kind?: non-empty-string,
     *     layer?: non-empty-string,
     *     len?: int,
     *     path?: non-empty-string,
     *     readable: string,
     *     root?: non-empty-string,
     *     sourceId?: non-empty-string
     * }>
     */
    private static function compiledSourceFileList(array $sourceFiles): array
    {
        $out = [];

        foreach ($sourceFiles as $sourceFile) {
            if (!\is_array($sourceFile)) {
                continue;
            }

            $normalized = [
                'exists' => self::boolToken($sourceFile['exists'] ?? false),
                'hash' => self::safeSha256OrNull($sourceFile['hash'] ?? null),
                'kind' => self::safeTokenOrNull($sourceFile['kind'] ?? null),
                'layer' => self::safeTokenOrNull($sourceFile['layer'] ?? null),
                'len' => self::safeIntOrNull($sourceFile['len'] ?? null),
                'path' => self::safeRelativePathOrNull($sourceFile['path'] ?? null),
                'readable' => self::boolToken($sourceFile['readable'] ?? false),
                'root' => self::safeRootOrNull($sourceFile['root'] ?? null),
                'sourceId' => self::safeSourceIdOrNull($sourceFile['sourceId'] ?? null),
            ];

            $normalized = \array_filter(
                $normalized,
                static fn (mixed $value): bool => $value !== null,
            );

            \ksort($normalized, \SORT_STRING);

            if ($normalized !== []) {
                $out[] = $normalized;
            }
        }

        \usort(
            $out,
            static fn (array $a, array $b): int => \strcmp(
                (string)($a['sourceId'] ?? ''),
                (string)($b['sourceId'] ?? '')
            )
                ?: \strcmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''))
                    ?: \strcmp((string)($a['layer'] ?? ''), (string)($b['layer'] ?? ''))
                        ?: \strcmp((string)($a['kind'] ?? ''), (string)($b['kind'] ?? '')),
        );

        return $out;
    }

    private static function boolToken(mixed $value): string
    {
        return $value === true
            ? self::EXISTS_TRUE
            : self::EXISTS_FALSE;
    }

    private static function safeSha256OrNull(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        if (\preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $candidates
     * @param list<non-empty-string> $skeletonIgnorePrefixes
     *
     * @return list<array<string, mixed>>
     */
    private function sourceCandidateList(
        string $kind,
        array $candidates,
        string $skeletonRoot,
        array $skeletonIgnorePrefixes,
    ): array {
        $out = [];

        foreach ($candidates as $candidate) {
            if (!\is_array($candidate)) {
                continue;
            }

            $out[] = $this->sourceCandidate(
                kind: $kind,
                candidate: $candidate,
                skeletonRoot: $skeletonRoot,
                skeletonIgnorePrefixes: $skeletonIgnorePrefixes,
            );
        }

        \usort(
            $out,
            static function (array $a, array $b): int {
                return \strcmp((string)($a['sourceId'] ?? ''), (string)($b['sourceId'] ?? ''))
                    ?: \strcmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''))
                        ?: \strcmp((string)($a['kind'] ?? ''), (string)($b['kind'] ?? ''));
            },
        );

        return $out;
    }

    /**
     * @param array<string,mixed> $candidate
     * @param list<non-empty-string> $skeletonIgnorePrefixes
     *
     * @return array<string, mixed>
     */
    private function sourceCandidate(
        string $kind,
        array $candidate,
        string $skeletonRoot,
        array $skeletonIgnorePrefixes,
    ): array {
        $filesystemPath = $candidate['filesystemPath'] ?? null;
        $sourceId = self::candidateSourceId($kind, $candidate);

        $metadata = [
            'kind' => $kind,
            'root' => self::safeRootOrNull($candidate['root'] ?? null),
            'packageId' => self::safeTokenOrNull($candidate['packageId'] ?? null),
            'moduleId' => self::safeTokenOrNull($candidate['moduleId'] ?? null),
            'path' => self::safeRelativePathOrNull($candidate['path'] ?? null),
            'sourceId' => $sourceId,
            'precedence' => self::safeIntOrNull($candidate['precedence'] ?? null),
        ];

        if (!\is_string($filesystemPath) || $filesystemPath === '') {
            $metadata['exists'] = self::EXISTS_FALSE;
            $metadata['reason'] = 'missing_filesystem_path';

            \ksort($metadata, \SORT_STRING);

            return $metadata;
        }

        $metadata += $this->contentFingerprintForExplicitCandidate(
            filesystemPath: $filesystemPath,
            skeletonRoot: $skeletonRoot,
            skeletonIgnorePrefixes: $skeletonIgnorePrefixes,
        );

        \ksort($metadata, \SORT_STRING);

        return $metadata;
    }

    /**
     * @param list<non-empty-string> $skeletonIgnorePrefixes
     *
     * @return array<string, mixed>
     */
    private function contentFingerprintForExplicitCandidate(
        string $filesystemPath,
        string $skeletonRoot,
        array $skeletonIgnorePrefixes,
    ): array {
        if (@\is_link($filesystemPath)) {
            $this->fileLister->listFileCandidate($filesystemPath);
        }

        if (!@\file_exists($filesystemPath)) {
            return [
                'exists' => self::EXISTS_FALSE,
            ];
        }

        if (@\is_dir($filesystemPath)) {
            $entries = [];

            foreach (
                $this->fileLister->listFiles(
                    declaredRoot: $filesystemPath,
                    skipRelativePath: self::skeletonIgnoreCallback(
                        declaredRoot: $filesystemPath,
                        skeletonRoot: $skeletonRoot,
                        skeletonIgnorePrefixes: $skeletonIgnorePrefixes,
                    ),
                ) as $relativePath
            ) {
                $file = self::joinPath($filesystemPath, $relativePath);

                $entries[] = self::fileFingerprintEntry($relativePath, $file);
            }

            \usort(
                $entries,
                static fn (array $a, array $b): int => \strcmp($a['path'], $b['path']),
            );

            return [
                'exists' => self::EXISTS_TRUE,
                'contentKind' => 'directory',
                'files' => $entries,
                'fileCount' => \count($entries),
            ];
        }

        if (!@\is_file($filesystemPath)) {
            return [
                'exists' => self::EXISTS_TRUE,
                'contentKind' => 'invalid',
            ];
        }

        $this->fileLister->listFileCandidate($filesystemPath);

        return [
            'exists' => self::EXISTS_TRUE,
            'contentKind' => 'file',
            'file' => self::fileFingerprintEntry(self::basename($filesystemPath), $filesystemPath),
        ];
    }

    /**
     * @param list<non-empty-string> $skeletonIgnorePrefixes
     *
     * @return null|\Closure(non-empty-string): bool
     */
    private static function skeletonIgnoreCallback(
        string $declaredRoot,
        string $skeletonRoot,
        array $skeletonIgnorePrefixes,
    ): ?\Closure {
        if ($skeletonIgnorePrefixes === []) {
            return null;
        }

        $normalizedSkeletonRoot = self::normalizeSkeletonRoot($skeletonRoot);
        $normalizedDeclaredRoot = self::normalizeSkeletonRoot($declaredRoot);

        if ($normalizedDeclaredRoot === $normalizedSkeletonRoot) {
            $declaredRootSkeletonRelative = '';
        } else {
            $declaredRootSkeletonRelative = self::skeletonRelativePathOrNull(
                skeletonRoot: $normalizedSkeletonRoot,
                filesystemPath: $normalizedDeclaredRoot,
            );

            if ($declaredRootSkeletonRelative === null) {
                return null;
            }
        }

        return static function (string $candidateRelativePath) use (
            $declaredRootSkeletonRelative,
            $skeletonIgnorePrefixes,
        ): bool {
            $normalizedCandidateRelativePath = \str_replace('\\', '/', $candidateRelativePath);

            $candidateSkeletonRelativePath = $declaredRootSkeletonRelative === ''
                ? $normalizedCandidateRelativePath
                : $declaredRootSkeletonRelative . '/' . $normalizedCandidateRelativePath;

            return self::isIgnoredSkeletonRelativePath(
                relativePath: $candidateSkeletonRelativePath,
                prefixes: $skeletonIgnorePrefixes,
            );
        };
    }

    /**
     * @return array{
     *     exists: string,
     *     hash?: non-empty-string,
     *     len?: int,
     *     path: string,
     *     readable: string
     * }
     */
    private static function fileFingerprintEntry(string $logicalPath, string $filesystemPath): array
    {
        $safePath = self::safeRelativePathOrNull($logicalPath) ?? '<path>';

        if (!@\is_file($filesystemPath) || !@\is_readable($filesystemPath)) {
            return [
                'exists' => self::EXISTS_TRUE,
                'path' => $safePath,
                'readable' => self::EXISTS_FALSE,
            ];
        }

        $contents = @\file_get_contents($filesystemPath);

        if (!\is_string($contents)) {
            return [
                'exists' => self::EXISTS_TRUE,
                'path' => $safePath,
                'readable' => self::EXISTS_FALSE,
            ];
        }

        $bytes = self::normalizeLf($contents);

        return [
            'exists' => self::EXISTS_TRUE,
            'path' => $safePath,
            'readable' => self::EXISTS_TRUE,
            'hash' => \hash('sha256', $bytes),
            'len' => \strlen($bytes),
        ];
    }

    /**
     * @param list<non-empty-string> $splitRoots
     *
     * @return list<non-empty-string>
     */
    private static function splitRoots(array $splitRoots): array
    {
        $out = [];

        foreach ($splitRoots as $root) {
            if (\is_string($root) && self::isSafeRoot($root)) {
                $out[] = $root;
            }
        }

        \usort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return \array_values(\array_unique($out));
    }

    /**
     * @return list<non-empty-string>
     */
    private static function skeletonIgnorePrefixes(array $kernelConfig): array
    {
        $value = $kernelConfig[self::KERNEL_KEY_FINGERPRINT][self::KERNEL_KEY_SKELETON_IGNORE_PREFIXES] ?? [];

        if (!\is_array($value) || !\array_is_list($value)) {
            throw new \InvalidArgumentException('fingerprint-skeleton-ignore-prefixes-invalid');
        }

        $prefixes = [];

        foreach ($value as $prefix) {
            if (!\is_string($prefix)) {
                throw new \InvalidArgumentException('fingerprint-skeleton-ignore-prefix-invalid');
            }

            $normalized = self::normalizeFingerprintRelativePath(
                value: $prefix,
                reason: 'fingerprint-skeleton-ignore-prefix-invalid',
            );

            if (\str_starts_with($normalized . '/', 'skeleton/')) {
                throw new \InvalidArgumentException('fingerprint-skeleton-ignore-prefix-invalid');
            }

            $prefixes[] = $normalized;
        }

        \usort($prefixes, static fn (string $a, string $b): int => \strcmp($a, $b));

        return \array_values(\array_unique($prefixes));
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeFingerprintRelativePath(string $value, string $reason): string
    {
        $normalized = \str_replace('\\', '/', $value);

        if (
            $normalized === ''
            || \strlen($normalized) > self::MAX_SAFE_STRING_BYTES
            || \trim($normalized) !== $normalized
            || \preg_match('/\s/u', $normalized) === 1
            || self::containsUnsafeStringBytes($normalized)
            || self::looksLikeAbsolutePath($normalized)
            || \str_contains($normalized, ':')
            || \str_contains($normalized, '://')
            || \str_contains($normalized, '//')
            || $normalized === '.'
            || $normalized === '..'
            || \str_starts_with($normalized, './')
            || \str_starts_with($normalized, '../')
            || \str_contains($normalized, '/./')
            || \str_contains($normalized, '/../')
            || \str_ends_with($normalized, '/.')
            || \str_ends_with($normalized, '/..')
        ) {
            throw new \InvalidArgumentException($reason);
        }

        return $normalized;
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeSkeletonRoot(string $skeletonRoot): string
    {
        $normalized = \str_replace('\\', '/', $skeletonRoot);
        $normalized = \rtrim($normalized, '/');

        if ($normalized === '') {
            return '/';
        }

        return $normalized;
    }

    private static function skeletonRelativePathOrNull(
        string $skeletonRoot,
        string $filesystemPath,
    ): ?string {
        $root = self::normalizeSkeletonRoot($skeletonRoot);
        $path = \rtrim(\str_replace('\\', '/', $filesystemPath), '/');

        if ($path === $root) {
            return null;
        }

        if ($root === '/') {
            $relative = \ltrim($path, '/');
        } else {
            if (!\str_starts_with($path, $root . '/')) {
                return null;
            }

            $relative = \substr($path, \strlen($root) + 1);
        }

        if ($relative === '' || !self::isSafeRelativePath($relative)) {
            return null;
        }

        return $relative;
    }

    /**
     * @param list<non-empty-string> $prefixes
     */
    private static function isIgnoredSkeletonRelativePath(
        string $relativePath,
        array $prefixes,
    ): bool {
        $relativePath = \str_replace('\\', '/', $relativePath);

        foreach ($prefixes as $prefix) {
            if ($relativePath === $prefix || \str_starts_with($relativePath, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dotenvCandidateList(BootstrapConfig $bootstrapConfig, array $kernelConfig): array
    {
        $out = [];

        foreach (self::dotenvFileNames($kernelConfig, $bootstrapConfig->appEnv()) as $fileName) {
            $filesystemPath = self::joinPath($bootstrapConfig->skeletonRoot(), $fileName);

            $candidate = [
                'kind' => self::SOURCE_KIND_DOTENV,
                'path' => $fileName,
                'sourceId' => 'dotenv/' . $fileName,
            ];

            $candidate += $this->contentFingerprintForExplicitCandidate(
                filesystemPath: $filesystemPath,
                skeletonRoot: self::normalizeSkeletonRoot($bootstrapConfig->skeletonRoot()),
                skeletonIgnorePrefixes: [],
            );

            \ksort($candidate, \SORT_STRING);

            $out[] = $candidate;
        }

        \usort(
            $out,
            static fn (array $a, array $b): int => \strcmp(
                (string)($a['sourceId'] ?? ''),
                (string)($b['sourceId'] ?? '')
            ),
        );

        return $out;
    }

    /**
     * @return list<non-empty-string>
     */
    private static function dotenvFileNames(array $kernelConfig, string $appEnv): array
    {
        $templates = $kernelConfig[self::KERNEL_KEY_ENV][self::KERNEL_KEY_DOTENV][self::KERNEL_KEY_FILES] ?? [];

        if (!\is_array($templates) || !\array_is_list($templates)) {
            return [];
        }

        $out = [];

        foreach ($templates as $template) {
            if (!\is_string($template)) {
                continue;
            }

            $candidate = \str_replace(self::ENV_TEMPLATE, $appEnv, $template);

            if (!self::isSafeDotenvFileName($candidate)) {
                continue;
            }

            $out[] = $candidate;
        }

        \usort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return \array_values(\array_unique($out));
    }

    /**
     * @param list<array<string,mixed>> $mappings
     *
     * @return list<array<string, mixed>>
     */
    private static function envOverlayMappings(array $mappings): array
    {
        $out = [];

        foreach ($mappings as $mapping) {
            if (!\is_array($mapping)) {
                continue;
            }

            $normalized = [
                'path' => self::safeKeyPathOrNull($mapping['path'] ?? null),
                'env' => self::safeEnvNameOrNull($mapping['env'] ?? null),
                'type' => self::safeTokenOrNull($mapping['type'] ?? null),
                'kind' => self::safeTokenOrNull($mapping['kind'] ?? null),
                'root' => self::safeRootOrNull($mapping['root'] ?? null),
                'sourceId' => self::safeSourceIdOrNull($mapping['sourceId'] ?? null),
                'precedence' => self::safeIntOrNull($mapping['precedence'] ?? null),
                'allowedValueCount' => self::safeCount($mapping['allowedValues'] ?? null),
            ];

            $normalized = \array_filter(
                $normalized,
                static fn (mixed $value): bool => $value !== null,
            );

            \ksort($normalized, \SORT_STRING);

            if ($normalized !== []) {
                $out[] = $normalized;
            }
        }

        \usort(
            $out,
            static fn (array $a, array $b): int => \strcmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''))
                ?: \strcmp((string)($a['env'] ?? ''), (string)($b['env'] ?? '')),
        );

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $mappings
     *
     * @return array<string, array<string, mixed>>
     */
    private static function envOverlaySourceMetadata(
        EnvRepositoryInterface $env,
        array $mappings,
    ): array {
        $envNames = [];

        foreach ($mappings as $mapping) {
            $name = $mapping['env'] ?? null;

            if (\is_string($name) && self::isSafeEnvName($name)) {
                $envNames[$name] = true;
            }
        }

        $out = [];

        foreach (\array_keys($envNames) as $name) {
            $source = $env->sourceOf($name);

            $out[$name] = [
                'hasSource' => $source instanceof ConfigValueSource,
                'source' => $source instanceof ConfigValueSource ? self::sourceMetadata($source) : null,
            ];
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    private static function missingCandidateCount(array $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            if (($item['exists'] ?? null) === self::EXISTS_FALSE) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param array<string,mixed> $candidate
     */
    private static function candidateSourceId(string $kind, array $candidate): string
    {
        $sourceId = $candidate['sourceId'] ?? null;

        if (\is_string($sourceId) && self::isSafeSourceId($sourceId)) {
            return $sourceId;
        }

        $path = $candidate['path'] ?? null;

        if (\is_string($path) && self::isSafeRelativePath($path)) {
            return $kind . '/' . \str_replace('/', '_', \str_replace('\\', '/', $path));
        }

        $root = $candidate['root'] ?? null;

        if (\is_string($root) && self::isSafeRoot($root)) {
            return $kind . '/' . $root;
        }

        return $kind . '/unknown';
    }

    private static function stableJsonBytes(mixed $value): string
    {
        return StableJsonEncoder::encodeStable($value);
    }

    private static function hashJsonLike(mixed $value): string
    {
        return \hash('sha256', self::stableJsonBytes(self::normalizeJsonLikeForHash($value)));
    }

    private static function normalizeJsonLikeForHash(mixed $value): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        if (\is_float($value)) {
            return [
                'type' => 'float-forbidden',
            ];
        }

        if (!\is_array($value)) {
            return [
                'type' => 'non-json-like',
            ];
        }

        if (\array_is_list($value)) {
            $out = [];

            foreach ($value as $item) {
                $out[] = self::normalizeJsonLikeForHash($item);
            }

            return $out;
        }

        $out = [];

        $keys = \array_keys($value);
        \usort(
            $keys,
            static fn (int|string $a, int|string $b): int => \strcmp((string)$a, (string)$b),
        );

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                continue;
            }

            $out[$key] = self::normalizeJsonLikeForHash($value[$key]);
        }

        return $out;
    }

    private static function jsonLikeType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            \is_bool($value) => 'bool',
            \is_int($value) => 'int',
            \is_string($value) => 'string',
            \is_array($value) && \array_is_list($value) => 'list',
            \is_array($value) => 'map',
            default => 'invalid',
        };
    }

    private static function safeCount(mixed $value): int
    {
        return \is_countable($value) ? \count($value) : 0;
    }

    private static function safeInt(mixed $value): int
    {
        return \is_int($value) ? $value : 0;
    }

    private static function safeIntOrNull(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }

    private static function safeKeyPath(string $parent, string $key): ?string
    {
        if (!self::isSafePathSegment($key)) {
            return null;
        }

        return $parent . '.' . $key;
    }

    private static function safeKeyPathOrNull(mixed $path): ?string
    {
        if (!\is_string($path) || $path === '') {
            return null;
        }

        if (\strlen($path) > self::MAX_SAFE_STRING_BYTES) {
            return null;
        }

        if (\preg_match(self::SAFE_KEY_PATH_PATTERN, $path) !== 1) {
            return null;
        }

        if (self::containsUnsafeStringBytes($path)) {
            return null;
        }

        return $path;
    }

    private static function safeRootOrNull(mixed $root): ?string
    {
        return \is_string($root) && self::isSafeRoot($root) ? $root : null;
    }

    private static function safeRootOrPlaceholder(string $root): string
    {
        return self::isSafeRoot($root) ? $root : '<root>';
    }

    private static function safeSourceIdOrNull(mixed $sourceId): ?string
    {
        return \is_string($sourceId) && self::isSafeSourceId($sourceId) ? $sourceId : null;
    }

    private static function safeSourceIdOrPlaceholder(string $sourceId): string
    {
        return self::isSafeSourceId($sourceId) ? $sourceId : '<source>';
    }

    private static function safeRelativePathOrNull(mixed $path): ?string
    {
        if (!\is_string($path)) {
            return null;
        }

        $normalized = \str_replace('\\', '/', $path);

        return self::isSafeRelativePath($normalized) ? $normalized : null;
    }

    private static function safeTokenOrNull(mixed $value): ?string
    {
        return \is_string($value) && self::isSafeSmallToken($value) ? $value : null;
    }

    private static function safeTokenOrPlaceholder(string $value): string
    {
        return self::isSafeSmallToken($value) ? $value : 'unknown';
    }

    private static function safeEnvNameOrNull(mixed $value): ?string
    {
        return \is_string($value) && self::isSafeEnvName($value) ? $value : null;
    }

    private static function safeMetadataStringOrNull(string $value): ?string
    {
        if (!self::isSafeMetadataString($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $meta
     *
     * @return array<string, null|bool|int|string>
     */
    private static function safeMetaMap(array $meta): array
    {
        $out = [];

        foreach ($meta as $key => $value) {
            if (!\is_string($key) || !self::isSafeSmallKey($key)) {
                continue;
            }

            if ($value === null || \is_bool($value) || \is_int($value)) {
                $out[$key] = $value;

                continue;
            }

            if (\is_string($value) && self::isSafeMetadataString($value)) {
                $out[$key] = $value;
            }
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    private static function isSafeRoot(string $root): bool
    {
        return \preg_match('/\A[a-z][a-z0-9_]*\z/', $root) === 1;
    }

    private static function isSafePathSegment(string $segment): bool
    {
        return \preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/', $segment) === 1
            && !self::isSensitiveString($segment);
    }

    private static function isSafeEnvName(string $value): bool
    {
        return \preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $value) === 1;
    }

    private static function isSafeSourceId(string $value): bool
    {
        return self::isSafeMetadataString($value)
            && !\str_contains($value, '://')
            && !\str_starts_with($value, '/')
            && !\str_starts_with($value, '\\')
            && \preg_match(self::WINDOWS_ABSOLUTE_PATH_PATTERN, $value) !== 1;
    }

    private static function isSafeRelativePath(string $path): bool
    {
        if (!self::isSafeMetadataString($path)) {
            return false;
        }

        if (
            \str_starts_with($path, '/')
            || \str_starts_with($path, '\\')
            || \str_contains($path, '://')
            || \preg_match(self::WINDOWS_ABSOLUTE_PATH_PATTERN, $path) === 1
        ) {
            return false;
        }

        if (
            $path === '.'
            || $path === '..'
            || \str_starts_with($path, './')
            || \str_starts_with($path, '../')
            || \str_contains($path, '/./')
            || \str_contains($path, '/../')
            || \str_ends_with($path, '/.')
            || \str_ends_with($path, '/..')
        ) {
            return false;
        }

        return true;
    }

    private static function isSafeDotenvFileName(string $fileName): bool
    {
        return self::isSafeMetadataString($fileName)
            && !\str_contains($fileName, '/')
            && !\str_contains($fileName, '\\')
            && !\str_contains($fileName, ':')
            && !\str_contains($fileName, '..');
    }

    private static function isSafeSmallToken(string $value): bool
    {
        if ($value === '' || \strlen($value) > self::MAX_SAFE_STRING_BYTES) {
            return false;
        }

        if (self::containsUnsafeStringBytes($value)) {
            return false;
        }

        if (\preg_match(self::SAFE_TOKEN_PATTERN, $value) !== 1) {
            return false;
        }

        if (self::looksLikeAbsolutePath($value)) {
            return false;
        }

        return true;
    }

    private static function isSafeSmallKey(string $value): bool
    {
        return \preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/', $value) === 1
            && !self::isSensitiveString($value);
    }

    private static function isSafeMetadataString(string $value): bool
    {
        if ($value === '' || \strlen($value) > self::MAX_SAFE_STRING_BYTES) {
            return false;
        }

        if (self::containsUnsafeStringBytes($value)) {
            return false;
        }

        if (self::looksLikeAbsolutePath($value)) {
            return false;
        }

        if (\preg_match('/\s/u', $value) === 1) {
            return false;
        }

        return true;
    }

    private static function containsUnsafeStringBytes(string $value): bool
    {
        return \preg_match(self::CONTROL_CHARACTER_PATTERN, $value) === 1;
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match(self::WINDOWS_ABSOLUTE_PATH_PATTERN, $value) === 1;
    }

    private static function isSensitiveString(string $value): bool
    {
        return \preg_match(self::SENSITIVE_KEY_PATTERN, $value) === 1;
    }

    private static function normalizeLf(string $bytes): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $bytes);
    }

    private static function joinPath(string $left, string $right): string
    {
        $left = \rtrim($left, '/\\');

        if ($left === '') {
            return \str_replace('\\', '/', $right);
        }

        return $left . '/' . \str_replace('\\', '/', $right);
    }

    private static function basename(string $path): string
    {
        $normalized = \str_replace('\\', '/', $path);
        $position = \strrpos($normalized, '/');

        if ($position === false) {
            return $normalized;
        }

        return \substr($normalized, $position + 1);
    }

    /**
     * @param array<string,mixed> $compiledConfig
     */
    private static function assertCompiledConfigShape(array $compiledConfig): void
    {
        if (!isset($compiledConfig[self::KEY_ENV_OVERLAY_MAPPINGS]) || !\is_array(
            $compiledConfig[self::KEY_ENV_OVERLAY_MAPPINGS]
        )) {
            throw new \InvalidArgumentException('fingerprint-compiled-env-overlay-mappings-missing');
        }

        if (!isset($compiledConfig[self::KEY_CONFIG_SOURCE_FILES]) || !\is_array(
            $compiledConfig[self::KEY_CONFIG_SOURCE_FILES]
        )) {
            throw new \InvalidArgumentException('fingerprint-compiled-config-source-files-missing');
        }

        if (!isset($compiledConfig[self::KEY_CONFIG]) || !\is_array($compiledConfig[self::KEY_CONFIG])) {
            throw new \InvalidArgumentException('fingerprint-compiled-config-missing');
        }

        if (!isset($compiledConfig[self::KEY_SOURCES]) || !\is_array($compiledConfig[self::KEY_SOURCES])) {
            throw new \InvalidArgumentException('fingerprint-compiled-sources-missing');
        }

        if (!isset($compiledConfig[self::KEY_OWNERS]) || !\is_array($compiledConfig[self::KEY_OWNERS])) {
            throw new \InvalidArgumentException('fingerprint-compiled-owners-missing');
        }

        if (!isset($compiledConfig[self::KEY_VALIDATION]) || !$compiledConfig[self::KEY_VALIDATION] instanceof ConfigValidationResult) {
            throw new \InvalidArgumentException('fingerprint-compiled-validation-missing');
        }

        if (!isset($compiledConfig[self::KEY_VALIDATION_SUBJECTS]) || !\is_array(
            $compiledConfig[self::KEY_VALIDATION_SUBJECTS]
        )) {
            throw new \InvalidArgumentException('fingerprint-compiled-validation-subjects-missing');
        }

        if (!isset($compiledConfig[self::KEY_VALIDATION_SUBJECTS]['validated']) || !\is_array(
            $compiledConfig[self::KEY_VALIDATION_SUBJECTS]['validated']
        )) {
            throw new \InvalidArgumentException('fingerprint-compiled-validation-subjects-invalid');
        }

        if (!isset($compiledConfig[self::KEY_VALIDATION_SUBJECTS]['unvalidated']) || !\is_array(
            $compiledConfig[self::KEY_VALIDATION_SUBJECTS]['unvalidated']
        )) {
            throw new \InvalidArgumentException('fingerprint-compiled-validation-subjects-invalid');
        }
    }
}
