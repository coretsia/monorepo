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

namespace Coretsia\Kernel\Artifacts\Builders;

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;

/**
 * Builds the Kernel-owned `config@1` artifact envelope.
 *
 * This builder consumes the already completed ConfigKernel::compile(...) result.
 * It intentionally does not run config loading, env overlay generation, config
 * merging, validation, or explain generation.
 *
 * Payload shape:
 *
 *     [
 *         'config' => <full merged global config>,
 *         'configSourceFiles' => <safe source-file metadata>,
 *         'envOverlayMappings' => <safe env-overlay mapping metadata>,
 *         'owners' => <safe owner metadata>,
 *         'sources' => <safe ConfigValueSource exports>,
 *         'validation' => <ConfigValidationResult::toArray()>,
 *         'validationSubjects' => <validated/unvalidated subject metadata>,
 *     ]
 *
 * The full merged global config payload is preserved as data, including
 * user-owned/custom roots. Validation/source metadata is converted to scalar
 * json-like arrays so PHP objects are never stored directly in the artifact
 * payload.
 *
 * This builder MUST NOT:
 *
 * - re-run ConfigKernel;
 * - re-run config merge;
 * - drop user-owned/custom roots from the config payload;
 * - mark unvalidated user-owned roots as framework-validated;
 * - include ConfigValidationResult or ConfigValueSource objects directly;
 * - include raw env values in provenance metadata;
 * - include raw filesystem absolute paths from source candidate arrays;
 * - assemble artifact envelopes directly.
 *
 * @internal
 */
final readonly class CompiledConfigBuilder
{
    public function __construct(
        private ArtifactEnvelopeFactory $envelopeFactory,
    ) {
    }

    /**
     * Builds a canonical `config@1` artifact envelope.
     *
     * @param array{
     *     config: array<string,mixed>,
     *     explain?: array<string,mixed>|null,
     *     owners: array<string, array<string, null|bool|int|string>>,
     *     sources: list<ConfigValueSource>,
     *     envOverlayMappings: list<array<string,mixed>>,
     *     configSourceFiles: list<array<string,mixed>>,
     *     validation: ConfigValidationResult,
     *     validationSubjects: array{
     *         unvalidated: list<array{root: non-empty-string, ownership: string, validation: string}>,
     *         validated: list<array{root: non-empty-string, ownership: string, validation: string}>
     *     }
     * } $compiledConfig ConfigKernel::compile(...) result.
     *
     * @return array{_meta: array<string, mixed>, payload: array<string, mixed>}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function build(
        array $compiledConfig,
        string $fingerprint,
    ): array {
        return $this->envelopeFactory->config(
            fingerprint: $fingerprint,
            payload: self::payload($compiledConfig),
        );
    }

    /**
     * @param array<string,mixed> $compiledConfig
     *
     * @return array<string,mixed>
     */
    private static function payload(array $compiledConfig): array
    {
        self::assertCompiledConfigShape($compiledConfig);

        $payload = [
            'config' => self::normalizeArray($compiledConfig['config']),
            'configSourceFiles' => self::configSourceFiles($compiledConfig['configSourceFiles']),
            'envOverlayMappings' => self::envOverlayMappings($compiledConfig['envOverlayMappings']),
            'owners' => self::owners($compiledConfig['owners']),
            'sources' => self::sources($compiledConfig['sources']),
            'validation' => $compiledConfig['validation']->toArray(),
            'validationSubjects' => self::validationSubjects($compiledConfig['validationSubjects']),
        ];

        \ksort($payload, \SORT_STRING);

        return $payload;
    }

    /**
     * @param array<string,mixed> $compiledConfig
     */
    private static function assertCompiledConfigShape(array $compiledConfig): void
    {
        if (!isset($compiledConfig['config']) || !\is_array($compiledConfig['config'])) {
            throw new \InvalidArgumentException('compiled-config-result-config-missing');
        }

        if (!isset($compiledConfig['owners']) || !\is_array($compiledConfig['owners'])) {
            throw new \InvalidArgumentException('compiled-config-result-owners-missing');
        }

        if (!isset($compiledConfig['sources']) || !\is_array($compiledConfig['sources']) || !\array_is_list(
            $compiledConfig['sources']
        )) {
            throw new \InvalidArgumentException('compiled-config-result-sources-missing');
        }

        if (!isset($compiledConfig['validation']) || !$compiledConfig['validation'] instanceof ConfigValidationResult) {
            throw new \InvalidArgumentException('compiled-config-result-validation-missing');
        }

        if (!isset($compiledConfig['validationSubjects']) || !\is_array($compiledConfig['validationSubjects'])) {
            throw new \InvalidArgumentException('compiled-config-result-validation-subjects-missing');
        }

        if (
            !isset($compiledConfig['envOverlayMappings'])
            || !\is_array($compiledConfig['envOverlayMappings'])
            || !\array_is_list($compiledConfig['envOverlayMappings'])
        ) {
            throw new \InvalidArgumentException('compiled-config-result-env-overlay-mappings-missing');
        }

        if (
            !isset($compiledConfig['configSourceFiles'])
            || !\is_array($compiledConfig['configSourceFiles'])
            || !\array_is_list($compiledConfig['configSourceFiles'])
        ) {
            throw new \InvalidArgumentException('compiled-config-result-source-files-missing');
        }
    }

    /**
     * Recursively normalizes array key ordering while preserving list order.
     *
     * @param array<array-key,mixed> $array
     *
     * @return array<array-key,mixed>
     */
    private static function normalizeArray(array $array): array
    {
        if (\array_is_list($array)) {
            $out = [];

            foreach ($array as $value) {
                $out[] = self::normalizeValue($value);
            }

            return $out;
        }

        $out = [];

        foreach ($array as $key => $value) {
            $out[$key] = self::normalizeValue($value);
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        return \is_array($value)
            ? self::normalizeArray($value)
            : $value;
    }

    /**
     * @param list<ConfigValueSource> $sources
     *
     * @return list<array<string,mixed>>
     */
    private static function sources(array $sources): array
    {
        $out = [];

        foreach ($sources as $source) {
            if (!$source instanceof ConfigValueSource) {
                throw new \InvalidArgumentException('compiled-config-source-invalid');
            }

            $entry = [
                'precedence' => $source->precedence(),
                'redacted' => $source->isRedacted(),
                'root' => self::safeRoot($source->root(), 'compiled-config-source-root-invalid'),
                'schemaVersion' => ConfigValueSource::SCHEMA_VERSION,
                'sourceId' => self::safeLogicalIdentifier($source->sourceId(), 'compiled-config-source-id-invalid'),
                'type' => $source->type()->value,
            ];

            $path = $source->path();

            if ($path !== null) {
                $entry['path'] = self::safeRelativePath($path, 'compiled-config-source-path-invalid');
            }

            $keyPath = $source->keyPath();

            if ($keyPath !== null) {
                $entry['keyPath'] = self::safeLogicalIdentifier($keyPath, 'compiled-config-source-key-path-invalid');
            }

            $directive = $source->directive();

            if ($directive !== null) {
                $entry['directive'] = self::safeToken($directive, 'compiled-config-source-directive-invalid');
            }

            $meta = self::metadataMap($source->meta());

            if ($meta !== []) {
                $entry['meta'] = $meta;
            }

            \ksort($entry, \SORT_STRING);

            $out[] = $entry;
        }

        \usort(
            $out,
            static fn (array $a, array $b): int => ($a['precedence'] <=> $b['precedence'])
                ?: \strcmp((string)$a['root'], (string)$b['root'])
                    ?: \strcmp((string)($a['keyPath'] ?? ''), (string)($b['keyPath'] ?? ''))
                        ?: \strcmp((string)$a['sourceId'], (string)$b['sourceId'])
                            ?: \strcmp((string)$a['type'], (string)$b['type'])
                                ?: \strcmp((string)($a['directive'] ?? ''), (string)($b['directive'] ?? '')),
        );

        return $out;
    }

    /**
     * @param array<string, array<string, null|bool|int|string>> $owners
     *
     * @return array<string, array<string, null|bool|int|string>>
     */
    private static function owners(array $owners): array
    {
        $out = [];

        foreach ($owners as $sourceId => $owner) {
            if (!\is_string($sourceId) || !self::isSafeLogicalIdentifier($sourceId)) {
                throw new \InvalidArgumentException('compiled-config-owner-source-id-invalid');
            }

            if (!\is_array($owner) || \array_is_list($owner)) {
                throw new \InvalidArgumentException('compiled-config-owner-invalid');
            }

            $normalized = [];

            foreach ($owner as $key => $value) {
                if (!\is_string($key) || !self::isSafeMetadataKey($key)) {
                    throw new \InvalidArgumentException('compiled-config-owner-key-invalid');
                }

                if ($value === null || \is_bool($value) || \is_int($value)) {
                    $normalized[$key] = $value;

                    continue;
                }

                if (!\is_string($value) || !self::isSafeMetadataString($value)) {
                    throw new \InvalidArgumentException('compiled-config-owner-value-invalid');
                }

                $normalized[$key] = $value;
            }

            \ksort($normalized, \SORT_STRING);

            $out[$sourceId] = $normalized;
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $mappings
     *
     * @return list<array<string, int|string>>
     */
    private static function envOverlayMappings(array $mappings): array
    {
        $out = [];

        foreach ($mappings as $mapping) {
            if (!\is_array($mapping) || \array_is_list($mapping)) {
                throw new \InvalidArgumentException('compiled-config-env-overlay-mapping-invalid');
            }

            $entry = [];

            foreach (['env', 'kind', 'path', 'root', 'sourceId', 'type'] as $key) {
                $value = $mapping[$key] ?? null;

                if (!\is_string($value)) {
                    throw new \InvalidArgumentException('compiled-config-env-overlay-mapping-field-invalid');
                }

                $entry[$key] = match ($key) {
                    'env' => self::safeEnvName($value, 'compiled-config-env-overlay-env-invalid'),
                    'path' => self::safeConfigPath($value, 'compiled-config-env-overlay-path-invalid'),
                    'root' => self::safeRoot($value, 'compiled-config-env-overlay-root-invalid'),
                    'sourceId' => self::safeLogicalIdentifier($value, 'compiled-config-env-overlay-source-id-invalid'),
                    default => self::safeToken($value, 'compiled-config-env-overlay-token-invalid'),
                };
            }

            $precedence = $mapping['precedence'] ?? null;

            if ($precedence !== null) {
                if (!\is_int($precedence) || $precedence < 0) {
                    throw new \InvalidArgumentException('compiled-config-env-overlay-precedence-invalid');
                }

                $entry['precedence'] = $precedence;
            }

            \ksort($entry, \SORT_STRING);

            $out[] = $entry;
        }

        \usort(
            $out,
            static fn (array $a, array $b): int => \strcmp((string)$a['path'], (string)$b['path'])
                ?: \strcmp((string)$a['env'], (string)$b['env'])
                    ?: \strcmp((string)$a['sourceId'], (string)$b['sourceId']),
        );

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $sourceFiles
     *
     * @return list<array<string, bool|int|string>>
     */
    private static function configSourceFiles(array $sourceFiles): array
    {
        $out = [];

        foreach ($sourceFiles as $sourceFile) {
            if (!\is_array($sourceFile) || \array_is_list($sourceFile)) {
                throw new \InvalidArgumentException('compiled-config-source-file-invalid');
            }

            foreach (['exists', 'kind', 'layer', 'path', 'readable', 'sourceId'] as $key) {
                if (!\array_key_exists($key, $sourceFile)) {
                    throw new \InvalidArgumentException('compiled-config-source-file-field-missing');
                }
            }

            if (!\is_bool($sourceFile['exists']) || !\is_bool($sourceFile['readable'])) {
                throw new \InvalidArgumentException('compiled-config-source-file-bool-invalid');
            }

            foreach (['kind', 'layer', 'path', 'sourceId'] as $key) {
                if (!\is_string($sourceFile[$key])) {
                    throw new \InvalidArgumentException('compiled-config-source-file-string-field-invalid');
                }
            }

            $entry = [
                'exists' => $sourceFile['exists'],
                'kind' => self::safeToken($sourceFile['kind'], 'compiled-config-source-file-kind-invalid'),
                'layer' => self::safeToken($sourceFile['layer'], 'compiled-config-source-file-layer-invalid'),
                'path' => self::safeRelativePath(
                    $sourceFile['path'],
                    'compiled-config-source-file-path-invalid'
                ),
                'readable' => $sourceFile['readable'],
                'sourceId' => self::safeLogicalIdentifier(
                    $sourceFile['sourceId'],
                    'compiled-config-source-file-source-id-invalid'
                ),
            ];

            if (isset($sourceFile['root'])) {
                if (!\is_string($sourceFile['root'])) {
                    throw new \InvalidArgumentException('compiled-config-source-file-root-invalid');
                }

                $entry['root'] = self::safeRoot($sourceFile['root'], 'compiled-config-source-file-root-invalid');
            }

            if (isset($sourceFile['hash'])) {
                if (!\is_string($sourceFile['hash']) || \preg_match('/\A[a-f0-9]{64}\z/', $sourceFile['hash']) !== 1) {
                    throw new \InvalidArgumentException('compiled-config-source-file-hash-invalid');
                }

                $entry['hash'] = $sourceFile['hash'];
            }

            if (isset($sourceFile['len'])) {
                if (!\is_int($sourceFile['len']) || $sourceFile['len'] < 0) {
                    throw new \InvalidArgumentException('compiled-config-source-file-len-invalid');
                }

                $entry['len'] = $sourceFile['len'];
            }

            \ksort($entry, \SORT_STRING);

            $out[] = $entry;
        }

        \usort(
            $out,
            static fn (array $a, array $b): int => \strcmp((string)$a['sourceId'], (string)$b['sourceId'])
                ?: \strcmp((string)$a['path'], (string)$b['path'])
                    ?: \strcmp((string)($a['root'] ?? ''), (string)($b['root'] ?? ''))
                        ?: \strcmp((string)$a['layer'], (string)$b['layer'])
                            ?: \strcmp((string)$a['kind'], (string)$b['kind']),
        );

        return $out;
    }

    /**
     * @param array{
     *     unvalidated: list<array{root: non-empty-string, ownership: string, validation: string}>,
     *     validated: list<array{root: non-empty-string, ownership: string, validation: string}>
     * } $validationSubjects
     *
     * @return array{
     *     unvalidated: list<array{ownership: string, root: string, validation: string}>,
     *     validated: list<array{ownership: string, root: string, validation: string}>
     * }
     */
    private static function validationSubjects(array $validationSubjects): array
    {
        foreach (['unvalidated', 'validated'] as $bucket) {
            if (!isset($validationSubjects[$bucket]) || !\is_array($validationSubjects[$bucket])) {
                throw new \InvalidArgumentException('compiled-config-validation-subject-bucket-missing');
            }
        }

        return [
            'unvalidated' => self::validationSubjectList($validationSubjects['unvalidated']),
            'validated' => self::validationSubjectList($validationSubjects['validated']),
        ];
    }

    /**
     * @param list<array{root: non-empty-string, ownership: string, validation: string}> $subjects
     *
     * @return list<array{ownership: string, root: string, validation: string}>
     */
    private static function validationSubjectList(array $subjects): array
    {
        if (!\array_is_list($subjects)) {
            throw new \InvalidArgumentException('compiled-config-validation-subject-list-invalid');
        }

        $out = [];

        foreach ($subjects as $subject) {
            if (!\is_array($subject) || \array_is_list($subject)) {
                throw new \InvalidArgumentException('compiled-config-validation-subject-invalid');
            }

            foreach (['ownership', 'root', 'validation'] as $key) {
                if (!isset($subject[$key]) || !\is_string($subject[$key])) {
                    throw new \InvalidArgumentException('compiled-config-validation-subject-field-invalid');
                }
            }

            $entry = [
                'ownership' => self::safeToken(
                    $subject['ownership'],
                    'compiled-config-validation-subject-ownership-invalid'
                ),
                'root' => self::safeRoot($subject['root'], 'compiled-config-validation-subject-root-invalid'),
                'validation' => self::safeToken(
                    $subject['validation'],
                    'compiled-config-validation-subject-validation-invalid'
                ),
            ];

            \ksort($entry, \SORT_STRING);

            $out[] = $entry;
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
     * @param array<string,mixed> $meta
     *
     * @return array<string,mixed>
     */
    private static function metadataMap(array $meta): array
    {
        $out = [];

        foreach ($meta as $key => $value) {
            if (!\is_string($key) || !self::isSafeMetadataKey($key)) {
                throw new \InvalidArgumentException('compiled-config-source-meta-key-invalid');
            }

            $out[$key] = self::metadataValue($value);
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    private static function metadataValue(mixed $value): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            if (!self::isSafeMetadataString($value)) {
                throw new \InvalidArgumentException('compiled-config-source-meta-string-invalid');
            }

            return $value;
        }

        if (\is_array($value)) {
            return self::metadataArray($value);
        }

        throw new \InvalidArgumentException('compiled-config-source-meta-value-invalid');
    }

    /**
     * @param array<array-key,mixed> $value
     *
     * @return array<array-key,mixed>
     */
    private static function metadataArray(array $value): array
    {
        if (\array_is_list($value)) {
            $out = [];

            foreach ($value as $item) {
                $out[] = self::metadataValue($item);
            }

            return $out;
        }

        $out = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key) || !self::isSafeMetadataKey($key)) {
                throw new \InvalidArgumentException('compiled-config-source-meta-array-key-invalid');
            }

            $out[$key] = self::metadataValue($item);
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    private static function safeRoot(string $root, string $reason): string
    {
        if (\preg_match('/\A[a-z][a-z0-9_]*\z/', $root) !== 1) {
            throw new \InvalidArgumentException($reason);
        }

        return $root;
    }

    private static function safeToken(string $token, string $reason): string
    {
        if (
            $token === ''
            || \strlen($token) > 128
            || \preg_match('/\A[A-Za-z0-9_.-]+\z/', $token) !== 1
        ) {
            throw new \InvalidArgumentException($reason);
        }

        return $token;
    }

    private static function safeEnvName(string $env, string $reason): string
    {
        if (\preg_match('/\A[A-Z][A-Z0-9_]*\z/', $env) !== 1) {
            throw new \InvalidArgumentException($reason);
        }

        return $env;
    }

    private static function safeConfigPath(string $path, string $reason): string
    {
        if (
            $path === ''
            || \strlen($path) > 256
            || \preg_match(
                '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63}|\[[0-9]{1,9}])*\z/',
                $path
            ) !== 1
        ) {
            throw new \InvalidArgumentException($reason);
        }

        return $path;
    }

    private static function safeLogicalIdentifier(string $value, string $reason): string
    {
        if (!self::isSafeLogicalIdentifier($value)) {
            throw new \InvalidArgumentException($reason);
        }

        return \str_replace('\\', '/', $value);
    }

    private static function safeRelativePath(string $path, string $reason): string
    {
        $normalized = \str_replace('\\', '/', $path);

        if (
            $normalized === ''
            || \strlen($normalized) > 256
            || self::containsUnsafeBytes($normalized)
            || self::looksLikeAbsolutePath($normalized)
            || \str_contains($normalized, '://')
            || \str_contains($normalized, ':')
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

    private static function isSafeLogicalIdentifier(string $value): bool
    {
        if ($value === '' || \strlen($value) > 256) {
            return false;
        }

        if (
            \preg_match('/\s/u', $value) === 1
            || self::containsUnsafeBytes($value)
            || self::looksLikeAbsolutePath($value)
            || \str_contains($value, ':')
            || \str_contains($value, '://')
        ) {
            return false;
        }

        $normalized = \str_replace('\\', '/', $value);

        return !(
            \str_contains($normalized, '//')
            || $normalized === '.'
            || $normalized === '..'
            || \str_starts_with($normalized, './')
            || \str_starts_with($normalized, '../')
            || \str_contains($normalized, '/./')
            || \str_contains($normalized, '/../')
            || \str_ends_with($normalized, '/.')
            || \str_ends_with($normalized, '/..')
        );
    }

    private static function isSafeMetadataKey(string $key): bool
    {
        return $key !== ''
            && \strlen($key) <= 64
            && \preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/', $key) === 1;
    }

    private static function isSafeMetadataString(string $value): bool
    {
        if ($value === '' || \strlen($value) > 256) {
            return false;
        }

        if (
            self::containsUnsafeBytes($value)
            || self::looksLikeAbsolutePath($value)
            || \str_contains($value, '://')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $value) === 1
        ) {
            return false;
        }

        return true;
    }

    private static function containsUnsafeBytes(string $value): bool
    {
        return \preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $value) === 1;
    }
}
