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

namespace Coretsia\Kernel\Artifacts\Verifier;

use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;

/**
 * Validates parsed Kernel-owned PHP artifact schemas.
 *
 * This validator receives arrays returned by PhpArtifactReader and validates:
 *
 * - canonical artifact envelope shape;
 * - canonical header fields;
 * - artifact-specific payload schemas for:
 *   - module-manifest@1;
 *   - config@1;
 *   - container@1.
 *
 * This class intentionally does not:
 *
 * - read files;
 * - include PHP artifacts;
 * - compare bytes;
 * - calculate fingerprints;
 * - build expected artifacts;
 * - construct ArtifactHeader objects for validation;
 * - rely on PHP object identity or runtime class type semantics;
 * - print output;
 * - emit observability.
 *
 * Validation is scalar/array-shape based only. Existing artifacts that use
 * artifact-specific alternative top-level shapes are rejected.
 *
 * @internal
 */
final readonly class ArtifactSchemaValidator
{
    private const int SCHEMA_VERSION_MODULE_MANIFEST = 1;
    private const int SCHEMA_VERSION_CONFIG = 1;
    private const int SCHEMA_VERSION_CONTAINER = 1;

    private const int MAX_SAFE_STRING_BYTES = 512;

    private const string HASH_PATTERN = '/\A[a-f0-9]{64}\z/';
    private const string FINGERPRINT_PATTERN = '/\A[a-z0-9][a-z0-9:._-]{0,255}\z/';
    private const string HEADER_NAME_PATTERN = '/\A[a-z][a-z0-9-]{0,63}\z/';
    private const string HEADER_GENERATOR_PATTERN = '/\A[a-z][a-z0-9_.\/-]{0,127}\z/';
    private const string ROOT_PATTERN = '/\A[a-z][a-z0-9_]*\z/';
    private const string TOKEN_PATTERN = '/\A[A-Za-z0-9_.-]{1,128}\z/';
    private const string ENV_NAME_PATTERN = '/\A[A-Z][A-Z0-9_]*\z/';
    private const string CONFIG_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63}|\[[0-9]{1,9}])*\z/';

    /**
     * @var list<string>
     */
    private const array ENVELOPE_KEYS = [
        '_meta',
        'payload',
    ];

    /**
     * @var list<string>
     */
    private const array HEADER_REQUIRED_KEYS = [
        'fingerprint',
        'generator',
        'name',
        'schemaVersion',
    ];

    /**
     * @var list<string>
     */
    private const array MODULE_MANIFEST_KEYS = [
        'app',
        'disabled',
        'enabled',
        'modules',
        'optionalMissing',
        'preset',
        'schemaVersion',
        'topologicalOrder',
        'warnings',
    ];

    /**
     * @var list<string>
     */
    private const array MODULE_ENTRY_KEYS = [
        'composerName',
        'conflicts',
        'moduleId',
        'requires',
    ];

    /**
     * @var list<string>
     */
    private const array MODULE_WARNING_KEYS = [
        'code',
        'moduleId',
        'preset',
        'reason',
    ];

    /**
     * @var list<string>
     */
    private const array CONFIG_PAYLOAD_KEYS = [
        'config',
        'configSourceFiles',
        'envOverlayMappings',
        'owners',
        'sources',
        'validation',
        'validationSubjects',
    ];

    /**
     * @var list<string>
     */
    private const array CONTAINER_PAYLOAD_KEYS = [
        'aliases',
        'compiled',
        'kind',
        'services',
        'tags',
    ];

    /**
     * Validates any Kernel-owned artifact envelope.
     *
     * @param array<int|string, mixed> $envelope
     *
     * @throws ArtifactInvalidException
     */
    public function validate(array $envelope): void
    {
        $this->validateEnvelope($envelope);

        /** @var array<string, mixed> $header */
        $header = $envelope['_meta'];

        /** @var array<string, mixed> $payload */
        $payload = $envelope['payload'];

        $name = $header['name'];
        $schemaVersion = $header['schemaVersion'];

        if (!\is_string($name) || !\is_int($schemaVersion)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_HEADER_INVALID,
            );
        }

        match ($name) {
            ArtifactEnvelopeFactory::ARTIFACT_MODULE_MANIFEST => $this->validateModuleManifestPayload(
                payload: $payload,
                schemaVersion: $schemaVersion,
            ),
            ArtifactEnvelopeFactory::ARTIFACT_CONFIG => $this->validateConfigPayload(
                payload: $payload,
                schemaVersion: $schemaVersion,
            ),
            ArtifactEnvelopeFactory::ARTIFACT_CONTAINER => $this->validateContainerPayload(
                payload: $payload,
                schemaVersion: $schemaVersion,
            ),
            default => throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_NAME_MISMATCH,
            ),
        };
    }

    /**
     * Validates an artifact envelope and asserts the expected artifact identity.
     *
     * @param array<int|string, mixed> $envelope
     *
     * @throws ArtifactInvalidException
     */
    public function validateExpected(
        array $envelope,
        string $expectedName,
        int $expectedSchemaVersion,
    ): void {
        $this->validate($envelope);

        /** @var array<string, mixed> $header */
        $header = $envelope['_meta'];

        if (($header['name'] ?? null) !== $expectedName) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_NAME_MISMATCH,
            );
        }

        if (($header['schemaVersion'] ?? null) !== $expectedSchemaVersion) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_VERSION_MISMATCH,
            );
        }
    }

    /**
     * @param array<int|string, mixed> $envelope
     *
     * @throws ArtifactInvalidException
     */
    private function validateEnvelope(array $envelope): void
    {
        if (\array_is_list($envelope) || \array_keys($envelope) !== self::ENVELOPE_KEYS) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_ENVELOPE_INVALID,
            );
        }

        if (
            !isset($envelope['_meta'])
            || !\is_array($envelope['_meta'])
            || \array_is_list($envelope['_meta'])
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_HEADER_INVALID,
            );
        }

        if (
            !isset($envelope['payload'])
            || !\is_array($envelope['payload'])
            || \array_is_list($envelope['payload'])
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        /** @var array<string, mixed> $header */
        $header = $envelope['_meta'];

        $this->validateHeader($header);
    }

    /**
     * @param array<string, mixed> $header
     *
     * @throws ArtifactInvalidException
     */
    private function validateHeader(array $header): void
    {
        $keys = \array_keys($header);
        \sort($keys, \SORT_STRING);

        $allowedKeys = self::HEADER_REQUIRED_KEYS;

        if (\array_key_exists('requires', $header)) {
            $allowedKeys[] = 'requires';
        }

        \sort($allowedKeys, \SORT_STRING);

        if ($keys !== $allowedKeys) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_HEADER_INVALID,
            );
        }

        if (
            !\is_string($header['name'])
            || \preg_match(self::HEADER_NAME_PATTERN, $header['name']) !== 1
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_HEADER_INVALID,
            );
        }

        if (!\is_int($header['schemaVersion']) || $header['schemaVersion'] < 1) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_HEADER_INVALID,
            );
        }

        if (
            !\is_string($header['fingerprint'])
            || \preg_match(self::FINGERPRINT_PATTERN, $header['fingerprint']) !== 1
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_FINGERPRINT_INVALID,
            );
        }

        if (
            !\is_string($header['generator'])
            || \preg_match(self::HEADER_GENERATOR_PATTERN, $header['generator']) !== 1
            || \str_contains($header['generator'], '..')
            || \str_contains($header['generator'], '//')
            || \str_contains($header['generator'], '://')
            || \str_contains($header['generator'], '\\')
            || self::looksLikeAbsolutePath($header['generator'])
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_HEADER_INVALID,
            );
        }

        if (\array_key_exists('requires', $header)) {
            if (
                !\is_array($header['requires'])
                || \array_is_list($header['requires'])
            ) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_HEADER_INVALID,
                );
            }

            self::assertJsonLikeMap($header['requires']);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ArtifactInvalidException
     */
    private function validateModuleManifestPayload(array $payload, int $schemaVersion): void
    {
        if ($schemaVersion !== self::SCHEMA_VERSION_MODULE_MANIFEST) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_VERSION_MISMATCH,
            );
        }

        self::assertExactMapKeys($payload, self::MODULE_MANIFEST_KEYS);

        if (($payload['schemaVersion'] ?? null) !== self::SCHEMA_VERSION_MODULE_MANIFEST) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        foreach (['app', 'preset'] as $key) {
            if (!\is_string($payload[$key]) || !self::isSafeText($payload[$key])) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }

        foreach (['disabled', 'enabled', 'optionalMissing', 'topologicalOrder'] as $key) {
            self::assertListOfSafeStrings($payload[$key]);
        }

        if (!self::isMapArray($payload['modules'])) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        foreach ($payload['modules'] as $moduleId => $entry) {
            if (!\is_string($moduleId) || !self::isSafeLogicalIdentifier($moduleId)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::validateModuleEntry($entry);
        }

        if (!\is_array($payload['warnings']) || !\array_is_list($payload['warnings'])) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        foreach ($payload['warnings'] as $warning) {
            self::validateModuleWarning($warning);
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function validateModuleEntry(mixed $entry): void
    {
        if (!\is_array($entry) || \array_is_list($entry)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertExactMapKeys($entry, self::MODULE_ENTRY_KEYS);

        foreach (['composerName', 'moduleId'] as $key) {
            if (!\is_string($entry[$key]) || !self::isSafeLogicalIdentifier($entry[$key])) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }

        self::assertListOfSafeStrings($entry['conflicts']);
        self::assertListOfSafeStrings($entry['requires']);
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function validateModuleWarning(mixed $warning): void
    {
        if (!\is_array($warning) || \array_is_list($warning)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertExactMapKeys($warning, self::MODULE_WARNING_KEYS);

        foreach (self::MODULE_WARNING_KEYS as $key) {
            if (!\is_string($warning[$key]) || !self::isSafeText($warning[$key])) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ArtifactInvalidException
     */
    private function validateConfigPayload(array $payload, int $schemaVersion): void
    {
        if ($schemaVersion !== self::SCHEMA_VERSION_CONFIG) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_VERSION_MISMATCH,
            );
        }

        self::assertExactMapKeys($payload, self::CONFIG_PAYLOAD_KEYS);

        if (!\is_array($payload['config']) || \array_is_list($payload['config'])) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        self::assertArtifactPayloadMap($payload['config']);
        $this->validateConfigSourceFiles($payload['configSourceFiles']);
        $this->validateEnvOverlayMappings($payload['envOverlayMappings']);
        $this->validateOwners($payload['owners']);
        $this->validateSources($payload['sources']);
        self::assertJsonLikeMapValue($payload['validation']);
        $this->validateValidationSubjects($payload['validationSubjects']);
    }

    /**
     * @throws ArtifactInvalidException
     */
    private function validateConfigSourceFiles(mixed $sourceFiles): void
    {
        if (!\is_array($sourceFiles) || !\array_is_list($sourceFiles)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        foreach ($sourceFiles as $sourceFile) {
            if (!\is_array($sourceFile) || \array_is_list($sourceFile)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            foreach (['exists', 'kind', 'layer', 'path', 'readable', 'sourceId'] as $required) {
                if (!\array_key_exists($required, $sourceFile)) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }
            }

            if (!\is_bool($sourceFile['exists']) || !\is_bool($sourceFile['readable'])) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if ($sourceFile['exists'] === false && $sourceFile['readable'] !== false) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if ($sourceFile['exists'] === true && $sourceFile['readable'] === true) {
                if (!\array_key_exists('hash', $sourceFile) || !\array_key_exists('len', $sourceFile)) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }
            }

            if ($sourceFile['readable'] === false) {
                if (\array_key_exists('hash', $sourceFile) || \array_key_exists('len', $sourceFile)) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }
            }

            if (
                !\is_string($sourceFile['kind'])
                || !self::isSafeToken($sourceFile['kind'])
                || !\is_string($sourceFile['layer'])
                || !self::isSafeToken($sourceFile['layer'])
                || !\is_string($sourceFile['path'])
                || !self::isSafeRelativePath($sourceFile['path'])
                || !\is_string($sourceFile['sourceId'])
                || !self::isSafeLogicalIdentifier($sourceFile['sourceId'])
            ) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (\array_key_exists('root', $sourceFile) && (
                !\is_string($sourceFile['root'])
                    || !self::isSafeRoot($sourceFile['root'])
            )) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (\array_key_exists('hash', $sourceFile) && (
                !\is_string($sourceFile['hash'])
                    || \preg_match(self::HASH_PATTERN, $sourceFile['hash']) !== 1
            )) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (\array_key_exists('len', $sourceFile) && (
                !\is_int($sourceFile['len'])
                    || $sourceFile['len'] < 0
            )) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::assertNoUnknownKeys(
                $sourceFile,
                ['exists', 'hash', 'kind', 'layer', 'len', 'path', 'readable', 'root', 'sourceId'],
            );
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private function validateEnvOverlayMappings(mixed $mappings): void
    {
        if (!\is_array($mappings) || !\array_is_list($mappings)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        foreach ($mappings as $mapping) {
            if (!\is_array($mapping) || \array_is_list($mapping)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            foreach (['env', 'kind', 'path', 'root', 'sourceId', 'type'] as $required) {
                if (!\array_key_exists($required, $mapping)) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }
            }

            if (
                !\is_string($mapping['env'])
                || \preg_match(self::ENV_NAME_PATTERN, $mapping['env']) !== 1
                || !\is_string($mapping['kind'])
                || !self::isSafeToken($mapping['kind'])
                || !\is_string($mapping['path'])
                || \preg_match(self::CONFIG_PATH_PATTERN, $mapping['path']) !== 1
                || !\is_string($mapping['root'])
                || !self::isSafeRoot($mapping['root'])
                || !\is_string($mapping['sourceId'])
                || !self::isSafeLogicalIdentifier($mapping['sourceId'])
                || !\is_string($mapping['type'])
                || !self::isSafeToken($mapping['type'])
            ) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (\array_key_exists('precedence', $mapping) && (
                !\is_int($mapping['precedence'])
                    || $mapping['precedence'] < 0
            )) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::assertNoUnknownKeys(
                $mapping,
                ['env', 'kind', 'path', 'precedence', 'root', 'sourceId', 'type'],
            );
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private function validateOwners(mixed $owners): void
    {
        if (!self::isMapArray($owners)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        foreach ($owners as $sourceId => $owner) {
            if (!\is_string($sourceId) || !self::isSafeLogicalIdentifier($sourceId)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (!\is_array($owner) || \array_is_list($owner)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::assertSafeMetadataMap($owner);
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private function validateSources(mixed $sources): void
    {
        if (!\is_array($sources) || !\array_is_list($sources)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        foreach ($sources as $source) {
            if (!\is_array($source) || \array_is_list($source)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            foreach (['precedence', 'redacted', 'root', 'schemaVersion', 'sourceId', 'type'] as $required) {
                if (!\array_key_exists($required, $source)) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }
            }

            if (
                !\is_int($source['precedence'])
                || $source['precedence'] < 0
                || !\is_bool($source['redacted'])
                || !\is_string($source['root'])
                || !self::isSafeRoot($source['root'])
                || !\is_int($source['schemaVersion'])
                || $source['schemaVersion'] < 1
                || !\is_string($source['sourceId'])
                || !self::isSafeLogicalIdentifier($source['sourceId'])
                || !\is_string($source['type'])
                || !self::isSafeToken($source['type'])
            ) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (\array_key_exists('path', $source) && (
                !\is_string($source['path'])
                    || !self::isSafeRelativePath($source['path'])
            )) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (\array_key_exists('keyPath', $source) && (
                !\is_string($source['keyPath'])
                    || \preg_match(self::CONFIG_PATH_PATTERN, $source['keyPath']) !== 1
            )) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (\array_key_exists('directive', $source) && (
                !\is_string($source['directive'])
                    || !self::isSafeToken($source['directive'])
            )) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (\array_key_exists('meta', $source)) {
                if (!\is_array($source['meta']) || \array_is_list($source['meta'])) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                self::assertSafeMetadataMap($source['meta']);
            }

            self::assertNoUnknownKeys(
                $source,
                [
                    'directive',
                    'keyPath',
                    'meta',
                    'path',
                    'precedence',
                    'redacted',
                    'root',
                    'schemaVersion',
                    'sourceId',
                    'type'
                ],
            );
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private function validateValidationSubjects(mixed $subjects): void
    {
        if (!\is_array($subjects) || \array_is_list($subjects)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertExactMapKeys($subjects, ['unvalidated', 'validated']);

        foreach (['unvalidated', 'validated'] as $bucket) {
            if (!\is_array($subjects[$bucket]) || !\array_is_list($subjects[$bucket])) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            foreach ($subjects[$bucket] as $subject) {
                if (!\is_array($subject) || \array_is_list($subject)) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                self::assertExactMapKeys($subject, ['ownership', 'root', 'validation']);

                if (
                    !\is_string($subject['ownership'])
                    || !self::isSafeToken($subject['ownership'])
                    || !\is_string($subject['root'])
                    || !self::isSafeRoot($subject['root'])
                    || !\is_string($subject['validation'])
                    || !self::isSafeToken($subject['validation'])
                ) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ArtifactInvalidException
     */
    private function validateContainerPayload(array $payload, int $schemaVersion): void
    {
        if ($schemaVersion !== self::SCHEMA_VERSION_CONTAINER) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_VERSION_MISMATCH,
            );
        }

        self::assertExactMapKeys($payload, self::CONTAINER_PAYLOAD_KEYS);

        if (
            ($payload['kind'] ?? null) !== 'stub'
            || ($payload['compiled'] ?? null) !== false
            || ($payload['services'] ?? null) !== []
            || ($payload['aliases'] ?? null) !== []
            || ($payload['tags'] ?? null) !== []
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $expectedKeys
     *
     * @throws ArtifactInvalidException
     */
    private static function assertExactMapKeys(array $map, array $expectedKeys): void
    {
        if (\array_is_list($map)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        $keys = \array_keys($map);
        \sort($keys, \SORT_STRING);

        $expected = $expectedKeys;
        \sort($expected, \SORT_STRING);

        if ($keys !== $expected) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $allowedKeys
     *
     * @throws ArtifactInvalidException
     */
    private static function assertNoUnknownKeys(array $map, array $allowedKeys): void
    {
        $allowed = [];

        foreach ($allowedKeys as $key) {
            $allowed[$key] = true;
        }

        foreach (\array_keys($map) as $key) {
            if (!\is_string($key) || !isset($allowed[$key])) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function assertListOfSafeStrings(mixed $value): void
    {
        if (!\is_array($value) || !\array_is_list($value)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        foreach ($value as $item) {
            if (!\is_string($item) || !self::isSafeText($item)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $value
     *
     * @throws ArtifactInvalidException
     */
    private static function assertArtifactPayloadMap(array $value): void
    {
        if (\array_is_list($value)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_PAYLOAD_INVALID,
                );
            }

            self::assertArtifactPayloadValue($item);
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function assertArtifactPayloadValue(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return;
        }

        if (\is_float($value) || \is_object($value) || \is_resource($value)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        if (!\is_array($value)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        if (\array_is_list($value)) {
            foreach ($value as $item) {
                self::assertArtifactPayloadValue($item);
            }

            return;
        }

        self::assertArtifactPayloadMap($value);
    }

    /**
     * @param array<string, mixed> $value
     *
     * @throws ArtifactInvalidException
     */
    private static function assertJsonLikeMap(array $value): void
    {
        if (\array_is_list($value)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        foreach ($value as $key => $item) {
            if (!\is_string($key) || !self::isSafeMetadataKey($key)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_PAYLOAD_INVALID,
                );
            }

            self::assertJsonLikeValue($item);
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function assertJsonLikeMapValue(mixed $value): void
    {
        if (!\is_array($value) || \array_is_list($value)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertJsonLikeMap($value);
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function assertJsonLikeValue(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            if (\is_string($value) && !self::isSafeText($value)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_PAYLOAD_INVALID,
                );
            }

            return;
        }

        if (\is_float($value) || \is_object($value) || \is_resource($value)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        if (!\is_array($value)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        if (\array_is_list($value)) {
            foreach ($value as $item) {
                self::assertJsonLikeValue($item);
            }

            return;
        }

        self::assertJsonLikeMap($value);
    }

    /**
     * @param array<string, mixed> $map
     *
     * @throws ArtifactInvalidException
     */
    private static function assertSafeMetadataMap(array $map): void
    {
        foreach ($map as $key => $value) {
            if (!\is_string($key) || !self::isSafeMetadataKey($key)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::assertSafeMetadataValue($value);
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function assertSafeMetadataValue(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return;
        }

        if (\is_string($value)) {
            if (!self::isSafeText($value)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            return;
        }

        if (\is_array($value)) {
            if (\array_is_list($value)) {
                foreach ($value as $item) {
                    self::assertSafeMetadataValue($item);
                }

                return;
            }

            self::assertSafeMetadataMap($value);

            return;
        }

        throw ArtifactInvalidException::withReason(
            ArtifactInvalidException::REASON_SCHEMA_INVALID,
        );
    }

    private static function isSafeRoot(string $value): bool
    {
        return \preg_match(self::ROOT_PATTERN, $value) === 1;
    }

    private static function isSafeToken(string $value): bool
    {
        return \preg_match(self::TOKEN_PATTERN, $value) === 1
            && !self::containsUnsafeBytes($value);
    }

    private static function isSafeText(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= self::MAX_SAFE_STRING_BYTES
            && !self::containsUnsafeBytes($value)
            && !self::looksLikeAbsolutePath($value)
            && !\str_contains($value, '://');
    }

    private static function isSafeMetadataKey(string $key): bool
    {
        return $key !== ''
            && \strlen($key) <= 64
            && \preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/', $key) === 1;
    }

    private static function isSafeRelativePath(string $value): bool
    {
        $normalized = \str_replace('\\', '/', $value);

        if (
            $normalized === ''
            || \strlen($normalized) > self::MAX_SAFE_STRING_BYTES
            || self::containsUnsafeBytes($normalized)
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
            return false;
        }

        return true;
    }

    private static function isSafeLogicalIdentifier(string $value): bool
    {
        $normalized = \str_replace('\\', '/', $value);

        if (
            $normalized === ''
            || \strlen($normalized) > self::MAX_SAFE_STRING_BYTES
            || \preg_match('/\s/u', $normalized) === 1
            || self::containsUnsafeBytes($normalized)
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
            return false;
        }

        return true;
    }

    private static function isMapArray(mixed $value): bool
    {
        return \is_array($value) && ($value === [] || !\array_is_list($value));
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
