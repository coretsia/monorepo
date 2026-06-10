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

use Coretsia\Foundation\Serialization\StableJsonEncoder;

/**
 * Builds safe deterministic fingerprint explain data.
 *
 * FingerprintExplainer is a pure data builder for cache verification and
 * fingerprint diffs. It consumes already-safe fingerprint input structures and
 * returns safe structured metadata that platform/cli can render without
 * additional redaction.
 *
 * Explain output may include:
 *
 * - normalized logical source ids;
 * - normalized relative paths;
 * - config key paths;
 * - source type;
 * - hash/len metadata;
 * - validation status;
 * - fingerprint policy entries such as skeleton ignore prefixes;
 * - fixed reason tokens such as missing, changed, extra, invalid.
 *
 * Explain output MUST NOT include:
 *
 * - raw config values;
 * - raw env values;
 * - dotenv values;
 * - secrets;
 * - raw payloads;
 * - raw SQL;
 * - absolute paths;
 * - PHP warning text;
 * - previous throwable messages.
 *
 * This class does not calculate artifact fingerprints, read files, read env,
 * emit logs/spans/metrics, print output, or depend on platform/cli.
 *
 * @internal
 */
final readonly class FingerprintExplainer
{
    public const int SCHEMA_VERSION = 1;

    private const string REASON_MISSING = 'missing';
    private const string REASON_CHANGED = 'changed';
    private const string REASON_EXTRA = 'extra';
    private const string REASON_INVALID = 'invalid';

    private const int MAX_SAFE_COUNT = 1_000_000_000;
    private const int MAX_SAFE_STRING_BYTES = 256;

    private const string SAFE_TOKEN_PATTERN = '/\A[A-Za-z0-9_.\/-]{1,256}\z/';
    private const string SAFE_CONFIG_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63}|\[[0-9]{1,9}])*\z/';
    private const string SAFE_HASH_PATTERN = '/\A[a-f0-9]{32,128}\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string WINDOWS_ABSOLUTE_PATH_PATTERN = '/\A[A-Za-z]:[\/\\\\]/';

    /**
     * Builds safe explain data for a single fingerprint input.
     *
     * @param array<string, mixed> $fingerprintInput Safe fingerprint input
     *                                               produced by
     *                                               ConfigFingerprintInputBuilder.
     *
     * @return array{
     *     schemaVersion: int,
     *     entries: list<array<string, bool|int|string>>,
     *     summary: array{
     *         entryCount: int,
     *         missingCount: int,
     *         changedCount: int,
     *         extraCount: int,
     *         invalidCount: int
     *     }
     * }
     */
    public function explain(array $fingerprintInput): array
    {
        $entries = [];

        self::collectBucketFingerprints($fingerprintInput, $entries);
        self::collectFingerprintPolicyEntries($fingerprintInput['fingerprintPolicy'] ?? null, $entries);
        self::collectCompiledConfigEntries($fingerprintInput['compiledConfig'] ?? null, $entries);
        self::collectSourceCandidateEntries($fingerprintInput['sourceCandidates'] ?? null, $entries);
        self::collectDotenvCandidateEntries($fingerprintInput['dotenvCandidates'] ?? null, $entries);
        self::collectEnvOverlayEntries($fingerprintInput['envOverlay'] ?? null, $entries);
        self::collectValidationSubjectEntries(
            $fingerprintInput['compiledConfig']['validationSubjects'] ?? null,
            $entries
        );

        return self::result($entries);
    }

    /**
     * Builds safe deterministic diff explain data between two fingerprint inputs.
     *
     * The returned entries use only fixed reason tokens:
     *
     * - missing: expected entry is absent from the actual input;
     * - extra: actual entry is absent from the expected input;
     * - changed: both entries exist but their safe metadata differs.
     *
     * @param array<string, mixed> $expectedFingerprintInput
     * @param array<string, mixed> $actualFingerprintInput
     *
     * @return array{
     *     schemaVersion: int,
     *     entries: list<array<string, bool|int|string>>,
     *     summary: array{
     *         entryCount: int,
     *         missingCount: int,
     *         changedCount: int,
     *         extraCount: int,
     *         invalidCount: int
     *     }
     * }
     */
    public function diff(
        array $expectedFingerprintInput,
        array $actualFingerprintInput,
    ): array {
        $expected = self::entryMap($this->explain($expectedFingerprintInput)['entries']);
        $actual = self::entryMap($this->explain($actualFingerprintInput)['entries']);

        $entries = [];

        foreach ($expected as $key => $expectedEntry) {
            if (!isset($actual[$key])) {
                $entries[] = self::withReason($expectedEntry, self::REASON_MISSING);

                continue;
            }

            if (self::entryBytes($expectedEntry) !== self::entryBytes($actual[$key])) {
                $entries[] = self::withReason($expectedEntry, self::REASON_CHANGED);
            }
        }

        foreach ($actual as $key => $actualEntry) {
            if (isset($expected[$key])) {
                continue;
            }

            $entries[] = self::withReason($actualEntry, self::REASON_EXTRA);
        }

        return self::result($entries);
    }

    /**
     * Builds a deterministic invalid explain result for cache verifier failure
     * paths that cannot safely expose the underlying throwable/message.
     *
     * @param array<string, mixed> $context Optional safe context. Unsafe fields
     *                                      are ignored.
     *
     * @return array{
     *     schemaVersion: int,
     *     entries: list<array<string, bool|int|string>>,
     *     summary: array{
     *         entryCount: int,
     *         missingCount: int,
     *         changedCount: int,
     *         extraCount: int,
     *         invalidCount: int
     *     }
     * }
     */
    public function invalid(array $context = []): array
    {
        $entry = [
            'kind' => 'invalid',
            'reason' => self::REASON_INVALID,
        ];

        $safeSourceId = self::safeLogicalIdentifierOrNull($context['sourceId'] ?? null);

        if ($safeSourceId !== null) {
            $entry['sourceId'] = $safeSourceId;
        }

        $safePath = self::safeRelativePathOrNull($context['path'] ?? null);

        if ($safePath !== null) {
            $entry['path'] = $safePath;
        }

        $safeKeyPath = self::safeConfigPathOrNull($context['keyPath'] ?? null);

        if ($safeKeyPath !== null) {
            $entry['keyPath'] = $safeKeyPath;
        }

        $safeSourceType = self::safeTokenOrNull($context['sourceType'] ?? null);

        if ($safeSourceType !== null) {
            $entry['sourceType'] = $safeSourceType;
        }

        return self::result([
            self::entry($entry),
        ]);
    }

    /**
     * @param array<string, mixed> $fingerprintInput
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectBucketFingerprints(
        array $fingerprintInput,
        array &$entries,
    ): void {
        $bucketNames = \array_keys($fingerprintInput);

        \usort(
            $bucketNames,
            static fn (int|string $left, int|string $right): int => \strcmp((string)$left, (string)$right),
        );

        foreach ($bucketNames as $bucketName) {
            if (!\is_string($bucketName)) {
                continue;
            }

            $safeBucket = self::safeTokenOrNull($bucketName);

            if ($safeBucket === null) {
                continue;
            }

            if ($bucketName === 'observabilityMetadata') {
                continue;
            }

            $bucketValue = $fingerprintInput[$bucketName];

            try {
                $bytes = StableJsonEncoder::encodeStable($bucketValue);
            } catch (\Throwable) {
                $entries[] = self::entry([
                    'kind' => 'bucket',
                    'bucket' => $safeBucket,
                    'reason' => self::REASON_INVALID,
                ]);

                continue;
            }

            $entries[] = self::entry([
                'kind' => 'bucket',
                'bucket' => $safeBucket,
                'hash' => \hash('sha256', $bytes),
                'len' => \strlen($bytes),
            ]);
        }
    }

    /**
     * @param mixed $fingerprintPolicy
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectFingerprintPolicyEntries(
        mixed $fingerprintPolicy,
        array &$entries,
    ): void {
        if (!\is_array($fingerprintPolicy) || \array_is_list($fingerprintPolicy)) {
            return;
        }

        $prefixes = $fingerprintPolicy['skeletonIgnorePrefixes'] ?? null;

        if (!\is_array($prefixes) || !\array_is_list($prefixes)) {
            $entries[] = self::entry([
                'kind' => 'fingerprint_policy',
                'sourceType' => 'skeleton_ignore_prefixes',
                'reason' => self::REASON_INVALID,
            ]);

            return;
        }

        foreach ($prefixes as $index => $prefix) {
            if ($index > self::MAX_SAFE_COUNT) {
                $entries[] = self::entry([
                    'kind' => 'fingerprint_policy',
                    'sourceType' => 'skeleton_ignore_prefix',
                    'reason' => self::REASON_INVALID,
                ]);

                continue;
            }

            if (!\is_string($prefix)) {
                $entries[] = self::entry([
                    'kind' => 'fingerprint_policy',
                    'keyPath' => 'fingerprintPolicy.skeletonIgnorePrefixes[' . $index . ']',
                    'sourceType' => 'skeleton_ignore_prefix',
                    'reason' => self::REASON_INVALID,
                ]);

                continue;
            }

            $safePath = self::safeRelativePathOrNull($prefix);

            if ($safePath === null) {
                $entries[] = self::entry([
                    'kind' => 'fingerprint_policy',
                    'keyPath' => 'fingerprintPolicy.skeletonIgnorePrefixes[' . $index . ']',
                    'sourceType' => 'skeleton_ignore_prefix',
                    'reason' => self::REASON_INVALID,
                ]);

                continue;
            }

            $entries[] = self::entry([
                'kind' => 'fingerprint_policy',
                'keyPath' => 'fingerprintPolicy.skeletonIgnorePrefixes[' . $index . ']',
                'path' => $safePath,
                'sourceType' => 'skeleton_ignore_prefix',
            ]);
        }
    }

    /**
     * @param mixed $compiledConfig
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectCompiledConfigEntries(
        mixed $compiledConfig,
        array &$entries,
    ): void {
        if (!\is_array($compiledConfig)) {
            return;
        }

        self::collectConfigValueFingerprintEntries(
            $compiledConfig['valueFingerprints'] ?? null,
            $entries,
        );

        self::collectConfigSourceEntries(
            $compiledConfig['sources'] ?? null,
            $entries,
        );
    }

    /**
     * @param mixed $valueFingerprints
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectConfigValueFingerprintEntries(
        mixed $valueFingerprints,
        array &$entries,
    ): void {
        if (!\is_array($valueFingerprints)) {
            return;
        }

        $keyPaths = \array_keys($valueFingerprints);

        \usort(
            $keyPaths,
            static fn (int|string $left, int|string $right): int => \strcmp((string)$left, (string)$right),
        );

        foreach ($keyPaths as $keyPath) {
            if (!\is_string($keyPath)) {
                continue;
            }

            $safeKeyPath = self::safeConfigPathOrNull($keyPath);

            if ($safeKeyPath === null) {
                continue;
            }

            $fingerprint = $valueFingerprints[$keyPath];

            if (!\is_array($fingerprint)) {
                continue;
            }

            $entry = [
                'kind' => 'config_value',
                'keyPath' => $safeKeyPath,
            ];

            $hash = self::safeHashOrNull($fingerprint['hash'] ?? null);

            if ($hash !== null) {
                $entry['hash'] = $hash;
            }

            $len = self::safeLenOrNull($fingerprint['len'] ?? null);

            if ($len !== null) {
                $entry['len'] = $len;
            }

            $type = self::safeTokenOrNull($fingerprint['type'] ?? null);

            if ($type !== null) {
                $entry['sourceType'] = $type;
            }

            $entries[] = self::entry($entry);
        }
    }

    /**
     * @param mixed $sources
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectConfigSourceEntries(
        mixed $sources,
        array &$entries,
    ): void {
        if (!\is_array($sources)) {
            return;
        }

        foreach ($sources as $source) {
            if (!\is_array($source)) {
                continue;
            }

            $entry = [
                'kind' => 'config_source',
            ];

            self::addSafeCommonSourceFields($entry, $source);

            $meta = $source['meta'] ?? null;

            if (\is_array($meta)) {
                $hash = self::safeHashOrNull($meta['hash'] ?? null);

                if ($hash !== null) {
                    $entry['hash'] = $hash;
                }

                $len = self::safeLenOrNull($meta['length'] ?? null);

                if ($len !== null) {
                    $entry['len'] = $len;
                }
            }

            $entries[] = self::entry($entry);
        }
    }

    /**
     * @param mixed $sourceCandidates
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectSourceCandidateEntries(
        mixed $sourceCandidates,
        array &$entries,
    ): void {
        if (!\is_array($sourceCandidates)) {
            return;
        }

        $bucketNames = \array_keys($sourceCandidates);

        \usort(
            $bucketNames,
            static fn (int|string $left, int|string $right): int => \strcmp((string)$left, (string)$right),
        );

        foreach ($bucketNames as $bucketName) {
            if (!\is_string($bucketName)) {
                continue;
            }

            $safeBucket = self::safeTokenOrNull($bucketName);

            if ($safeBucket === null) {
                continue;
            }

            $bucket = $sourceCandidates[$bucketName];

            if (!\is_array($bucket)) {
                continue;
            }

            self::collectCandidateListEntries($safeBucket, $bucket, $entries);
        }
    }

    /**
     * @param mixed $dotenvCandidates
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectDotenvCandidateEntries(
        mixed $dotenvCandidates,
        array &$entries,
    ): void {
        if (!\is_array($dotenvCandidates)) {
            return;
        }

        self::collectCandidateListEntries('dotenv', $dotenvCandidates, $entries);
    }

    /**
     * @param list<mixed> $candidates
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectCandidateListEntries(
        string $bucket,
        array $candidates,
        array &$entries,
    ): void {
        foreach ($candidates as $candidate) {
            if (!\is_array($candidate)) {
                continue;
            }

            $entry = [
                'kind' => 'source_candidate',
                'bucket' => $bucket,
            ];

            self::addSafeCommonSourceFields($entry, $candidate);
            self::addSafeExistsFields($entry, $candidate);

            $file = $candidate['file'] ?? null;

            if (\is_array($file)) {
                self::addSafeHashLenPath($entry, $file);
            } else {
                self::addSafeHashLenPath($entry, $candidate);
            }

            $entries[] = self::entry($entry);

            $files = $candidate['files'] ?? null;

            if (!\is_array($files)) {
                continue;
            }

            foreach ($files as $fileEntry) {
                if (!\is_array($fileEntry)) {
                    continue;
                }

                $nested = [
                    'kind' => 'source_candidate_file',
                    'bucket' => $bucket,
                ];

                $sourceId = self::safeLogicalIdentifierOrNull($candidate['sourceId'] ?? null);

                if ($sourceId !== null) {
                    $nested['sourceId'] = $sourceId;
                }

                self::addSafeHashLenPath($nested, $fileEntry);
                self::addSafeExistsFields($nested, $fileEntry);

                $entries[] = self::entry($nested);
            }
        }
    }

    /**
     * @param mixed $envOverlay
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectEnvOverlayEntries(
        mixed $envOverlay,
        array &$entries,
    ): void {
        if (!\is_array($envOverlay)) {
            return;
        }

        $mappings = $envOverlay['mappings'] ?? null;

        if (\is_array($mappings)) {
            foreach ($mappings as $mapping) {
                if (!\is_array($mapping)) {
                    continue;
                }

                $entry = [
                    'kind' => 'env_overlay_mapping',
                ];

                self::addSafeCommonSourceFields($entry, $mapping);

                $entries[] = self::entry($entry);
            }
        }

        $sources = $envOverlay['sources'] ?? null;

        if (!\is_array($sources)) {
            return;
        }

        foreach ($sources as $sourceMetadata) {
            if (!\is_array($sourceMetadata)) {
                continue;
            }

            $source = $sourceMetadata['source'] ?? null;

            if (!\is_array($source)) {
                continue;
            }

            $entry = [
                'kind' => 'env_overlay_source',
            ];

            self::addSafeCommonSourceFields($entry, $source);

            $entries[] = self::entry($entry);
        }
    }

    /**
     * @param mixed $validationSubjects
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function collectValidationSubjectEntries(
        mixed $validationSubjects,
        array &$entries,
    ): void {
        if (!\is_array($validationSubjects)) {
            return;
        }

        foreach (['validated', 'unvalidated'] as $bucket) {
            $subjects = $validationSubjects[$bucket] ?? null;

            if (!\is_array($subjects)) {
                continue;
            }

            foreach ($subjects as $subject) {
                if (!\is_array($subject)) {
                    continue;
                }

                $root = self::safeRootOrNull($subject['root'] ?? null);

                if ($root === null) {
                    continue;
                }

                $validation = self::safeTokenOrNull($subject['validation'] ?? null)
                    ?? self::safeTokenOrNull($bucket)
                    ?? 'unknown';

                $entries[] = self::entry([
                    'kind' => 'validation_subject',
                    'keyPath' => $root,
                    'validation' => $validation,
                ]);
            }
        }
    }

    /**
     * @param array<string, bool|int|string> $entry
     * @param array<string, mixed> $source
     */
    private static function addSafeCommonSourceFields(
        array &$entry,
        array $source,
    ): void {
        $sourceId = self::safeLogicalIdentifierOrNull($source['sourceId'] ?? null);

        if ($sourceId !== null) {
            $entry['sourceId'] = $sourceId;
        }

        $path = self::safeRelativePathOrNull($source['path'] ?? null);

        if ($path !== null) {
            $entry['path'] = $path;
        }

        $keyPath = self::safeConfigPathOrNull($source['keyPath'] ?? $source['path'] ?? null);

        if ($keyPath !== null) {
            $entry['keyPath'] = $keyPath;
        }

        $sourceType = self::safeTokenOrNull($source['type'] ?? $source['sourceType'] ?? null);

        if ($sourceType !== null) {
            $entry['sourceType'] = $sourceType;
        }

        $root = self::safeRootOrNull($source['root'] ?? null);

        if ($root !== null && !isset($entry['keyPath'])) {
            $entry['keyPath'] = $root;
        }

        $validation = self::safeTokenOrNull($source['validation'] ?? null);

        if ($validation !== null) {
            $entry['validation'] = $validation;
        }
    }

    /**
     * @param array<string, bool|int|string> $entry
     * @param array<string, mixed> $source
     */
    private static function addSafeExistsFields(
        array &$entry,
        array $source,
    ): void {
        $exists = self::safeBoolTokenOrNull($source['exists'] ?? null);

        if ($exists !== null) {
            $entry['exists'] = $exists;

            if ($exists === 'false') {
                $entry['reason'] = self::REASON_MISSING;
            }
        }

        $readable = self::safeBoolTokenOrNull($source['readable'] ?? null);

        if ($readable !== null) {
            $entry['readable'] = $readable;
        }
    }

    /**
     * @param array<string, bool|int|string> $entry
     * @param array<string, mixed> $source
     */
    private static function addSafeHashLenPath(
        array &$entry,
        array $source,
    ): void {
        $hash = self::safeHashOrNull($source['hash'] ?? null);

        if ($hash !== null) {
            $entry['hash'] = $hash;
        }

        $len = self::safeLenOrNull($source['len'] ?? null);

        if ($len !== null) {
            $entry['len'] = $len;
        }

        $path = self::safeRelativePathOrNull($source['path'] ?? null);

        if ($path !== null) {
            $entry['path'] = $path;
        }
    }

    /**
     * @param array<string, bool|int|string> $entry
     *
     * @return array<string, bool|int|string>
     */
    private static function entry(array $entry): array
    {
        $normalized = [];

        foreach ($entry as $key => $value) {
            if (!\is_string($key) || $key === '') {
                continue;
            }

            if ($value === '' || $value === false) {
                continue;
            }

            if (\is_int($value)) {
                $normalized[$key] = self::safeCount($value);

                continue;
            }

            if (\is_bool($value)) {
                $normalized[$key] = $value;

                continue;
            }

            if (!\is_string($value)) {
                continue;
            }

            $safe = self::safeValueForKey($key, $value);

            if ($safe === null) {
                continue;
            }

            $normalized[$key] = $safe;
        }

        if (!isset($normalized['kind'])) {
            $normalized['kind'] = 'entry';
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    /**
     * @param list<array<string, bool|int|string>> $entries
     *
     * @return array{
     *     schemaVersion: int,
     *     entries: list<array<string, bool|int|string>>,
     *     summary: array{
     *         entryCount: int,
     *         missingCount: int,
     *         changedCount: int,
     *         extraCount: int,
     *         invalidCount: int
     *     }
     * }
     */
    private static function result(array $entries): array
    {
        \usort(
            $entries,
            static fn (array $left, array $right): int => \strcmp(self::entryKey($left), self::entryKey($right)),
        );

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'entries' => $entries,
            'summary' => self::summary($entries),
        ];
    }

    /**
     * @param list<array<string, bool|int|string>> $entries
     *
     * @return array<string, array<string, bool|int|string>>
     */
    private static function entryMap(array $entries): array
    {
        $map = [];

        foreach ($entries as $entry) {
            $map[self::entryKey($entry)] = $entry;
        }

        \ksort($map, \SORT_STRING);

        return $map;
    }

    /**
     * @param array<string, bool|int|string> $entry
     *
     * @return array<string, bool|int|string>
     */
    private static function withReason(array $entry, string $reason): array
    {
        $entry['reason'] = self::safeReason($reason);

        return self::entry($entry);
    }

    /**
     * @param array<string, bool|int|string> $entry
     */
    private static function entryKey(array $entry): string
    {
        return \implode(
            "\x1F",
            [
                (string)($entry['kind'] ?? ''),
                (string)($entry['bucket'] ?? ''),
                (string)($entry['sourceId'] ?? ''),
                (string)($entry['path'] ?? ''),
                (string)($entry['keyPath'] ?? ''),
                (string)($entry['sourceType'] ?? ''),
                (string)($entry['validation'] ?? ''),
            ],
        );
    }

    /**
     * @param array<string, bool|int|string> $entry
     */
    private static function entryBytes(array $entry): string
    {
        unset($entry['reason']);
        \ksort($entry, \SORT_STRING);

        try {
            return StableJsonEncoder::encodeStable($entry);
        } catch (\Throwable) {
            return 'invalid';
        }
    }

    /**
     * @param list<array<string, bool|int|string>> $entries
     *
     * @return array{
     *     entryCount: int,
     *     missingCount: int,
     *     changedCount: int,
     *     extraCount: int,
     *     invalidCount: int
     * }
     */
    private static function summary(array $entries): array
    {
        $summary = [
            'entryCount' => \count($entries),
            'missingCount' => 0,
            'changedCount' => 0,
            'extraCount' => 0,
            'invalidCount' => 0,
        ];

        foreach ($entries as $entry) {
            $reason = $entry['reason'] ?? null;

            if ($reason === self::REASON_MISSING) {
                ++$summary['missingCount'];

                continue;
            }

            if ($reason === self::REASON_CHANGED) {
                ++$summary['changedCount'];

                continue;
            }

            if ($reason === self::REASON_EXTRA) {
                ++$summary['extraCount'];

                continue;
            }

            if ($reason === self::REASON_INVALID) {
                ++$summary['invalidCount'];
            }
        }

        foreach ($summary as $key => $value) {
            $summary[$key] = self::safeCount($value);
        }

        return $summary;
    }

    private static function safeValueForKey(string $key, string $value): ?string
    {
        return match ($key) {
            'bucket', 'kind', 'reason', 'sourceType', 'validation' => self::safeTokenOrNull($value),
            'exists', 'readable' => self::safeBoolTokenOrNull($value),
            'hash' => self::safeHashOrNull($value),
            'keyPath' => self::safeConfigPathOrNull($value),
            'path' => self::safeRelativePathOrNull($value),
            'sourceId' => self::safeLogicalIdentifierOrNull($value),
            default => null,
        };
    }

    private static function safeReason(string $reason): string
    {
        return match ($reason) {
            self::REASON_MISSING,
            self::REASON_CHANGED,
            self::REASON_EXTRA,
            self::REASON_INVALID => $reason,
            default => self::REASON_INVALID,
        };
    }

    private static function safeTokenOrNull(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        if ($value === '' || \strlen($value) > self::MAX_SAFE_STRING_BYTES) {
            return null;
        }

        if (\preg_match(self::SAFE_TOKEN_PATTERN, $value) !== 1) {
            return null;
        }

        if (
            self::containsUnsafeBytes($value)
            || self::looksLikeAbsolutePath($value)
            || \str_contains($value, '://')
            || \str_contains($value, '//')
            || $value === '.'
            || $value === '..'
            || \str_starts_with($value, './')
            || \str_starts_with($value, '../')
            || \str_contains($value, '/./')
            || \str_contains($value, '/../')
            || \str_ends_with($value, '/.')
            || \str_ends_with($value, '/..')
        ) {
            return null;
        }

        return $value;
    }

    private static function safeHashOrNull(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        if (\preg_match(self::SAFE_HASH_PATTERN, $value) !== 1) {
            return null;
        }

        return $value;
    }

    private static function safeLenOrNull(mixed $value): ?int
    {
        if (!\is_int($value) || $value < 0) {
            return null;
        }

        return self::safeCount($value);
    }

    private static function safeBoolTokenOrNull(mixed $value): ?string
    {
        if ($value === true || $value === 'true') {
            return 'true';
        }

        if ($value === false || $value === 'false') {
            return 'false';
        }

        return null;
    }

    private static function safeConfigPathOrNull(mixed $path): ?string
    {
        if (!\is_string($path)) {
            return null;
        }

        if ($path === '' || \strlen($path) > self::MAX_SAFE_STRING_BYTES) {
            return null;
        }

        if (\preg_match(self::SAFE_CONFIG_PATH_PATTERN, $path) !== 1) {
            return null;
        }

        if (self::containsUnsafeBytes($path) || self::looksLikeAbsolutePath($path)) {
            return null;
        }

        return $path;
    }

    private static function safeRelativePathOrNull(mixed $path): ?string
    {
        if (!\is_string($path)) {
            return null;
        }

        $normalized = \str_replace('\\', '/', $path);

        if ($normalized === '' || \strlen($normalized) > self::MAX_SAFE_STRING_BYTES) {
            return null;
        }

        if (
            self::containsUnsafeBytes($normalized)
            || self::looksLikeAbsolutePath($normalized)
            || \str_contains($normalized, '://')
            || \str_contains($normalized, ':')
            || \str_contains($normalized, '//')
        ) {
            return null;
        }

        if (
            $normalized === '.'
            || $normalized === '..'
            || \str_starts_with($normalized, './')
            || \str_starts_with($normalized, '../')
            || \str_contains($normalized, '/./')
            || \str_contains($normalized, '/../')
            || \str_ends_with($normalized, '/.')
            || \str_ends_with($normalized, '/..')
        ) {
            return null;
        }

        return $normalized;
    }

    private static function safeLogicalIdentifierOrNull(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        if ($value === '' || \strlen($value) > self::MAX_SAFE_STRING_BYTES) {
            return null;
        }

        if (
            \preg_match('/\s/u', $value) === 1
            || self::containsUnsafeBytes($value)
            || self::looksLikeAbsolutePath($value)
            || \str_contains($value, ':')
            || \str_contains($value, '://')
        ) {
            return null;
        }

        $normalized = \str_replace('\\', '/', $value);

        if (
            \str_contains($normalized, '//')
            || $normalized === '.'
            || $normalized === '..'
            || \str_starts_with($normalized, './')
            || \str_starts_with($normalized, '../')
            || \str_contains($normalized, '/./')
            || \str_contains($normalized, '/../')
            || \str_ends_with($normalized, '/.')
            || \str_ends_with($normalized, '/..')
        ) {
            return null;
        }

        return $normalized;
    }

    private static function safeRootOrNull(mixed $root): ?string
    {
        if (!\is_string($root)) {
            return null;
        }

        return \preg_match('/\A[a-z][a-z0-9_]*\z/', $root) === 1
            ? $root
            : null;
    }

    private static function safeCount(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return \min($value, self::MAX_SAFE_COUNT);
    }

    private static function containsUnsafeBytes(string $value): bool
    {
        return \preg_match(self::CONTROL_CHARACTER_PATTERN, $value) === 1;
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match(self::WINDOWS_ABSOLUTE_PATH_PATTERN, $value) === 1;
    }
}
