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
    private const int MAX_CONTAINER_GRAPH_DEPTH = 32;
    private const int MAX_CONTAINER_MAP_KEYS = 8192;
    private const int MAX_CONTAINER_LIST_ITEMS = 8192;
    private const int MAX_CONTAINER_STRING_BYTES = 2048;

    private const string HASH_PATTERN = '/\A[a-f0-9]{64}\z/';
    private const string FINGERPRINT_PATTERN = '/\A[a-z0-9][a-z0-9:._-]{0,255}\z/';
    private const string HEADER_NAME_PATTERN = '/\A[a-z][a-z0-9-]{0,63}\z/';
    private const string HEADER_GENERATOR_PATTERN = '/\A[a-z][a-z0-9_.\/-]{0,127}\z/';
    private const string ROOT_PATTERN = '/\A[a-z][a-z0-9_]*\z/';
    private const string TOKEN_PATTERN = '/\A[A-Za-z0-9_.-]{1,128}\z/';
    private const string ENV_NAME_PATTERN = '/\A[A-Z][A-Z0-9_]*\z/';
    private const string CONFIG_PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63}|\[[0-9]{1,9}])*\z/';
    private const string CONTAINER_SERVICE_TYPE_CLASS = 'class';
    private const string CONTAINER_SERVICE_TYPE_FACTORY = 'factory';
    private const string CONTAINER_FACTORY_CLASS_METHOD = 'class-method';
    private const string CONTAINER_FACTORY_SERVICE_METHOD = 'service-method';
    private const string CONTAINER_TAG_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/';
    private const string CONTAINER_METHOD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/';
    private const string CONTAINER_CLASS_LIKE_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/';
    private const string CONTAINER_SOURCE_SNIPPET_PATTERN = '/<\?php|<\?=|\bfunction\s*\(|\bfn\s*\(|=>\s*\{|;\s*}/i';
    private const string CONTAINER_ENV_LIKE_PATTERN = '/\$\{[A-Z_][A-Z0-9_]*}|%env\(|\benv\s*\(/i';

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
        'parameters',
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
        self::assertMapKeysSortedByByteOrder($payload);

        if (($payload['kind'] ?? null) !== 'compiled') {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (($payload['compiled'] ?? null) !== true) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        $aliases = $payload['aliases'];
        $parameters = $payload['parameters'];
        $services = $payload['services'];
        $tags = $payload['tags'];

        self::validateContainerAliases($aliases);
        self::validateContainerParameters($parameters);
        self::validateContainerServices($services);
        self::validateContainerTags($tags);
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function validateContainerAliases(mixed $aliases): void
    {
        if (!self::isMapArray($aliases)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertMapKeysSortedByByteOrder($aliases);

        foreach ($aliases as $alias => $serviceId) {
            if (
                !\is_string($alias)
                || !self::isSafeContainerId($alias)
                || !\is_string($serviceId)
                || !self::isSafeContainerId($serviceId)
                || $alias === $serviceId
            ) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function validateContainerParameters(mixed $parameters): void
    {
        if (!self::isMapArray($parameters)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertMapKeysSortedByByteOrder($parameters);

        foreach ($parameters as $name => $value) {
            if (!\is_string($name) || !self::isSafeContainerParameterName($name)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::assertContainerGraphValue($value, 0);
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function validateContainerServices(mixed $services): void
    {
        if (!self::isMapArray($services)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertMapKeysSortedByByteOrder($services);

        foreach ($services as $serviceId => $definition) {
            if (!\is_string($serviceId) || !self::isSafeContainerId($serviceId)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::validateContainerServiceDefinition(
                serviceId: $serviceId,
                definition: $definition,
            );
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function validateContainerServiceDefinition(
        string $serviceId,
        mixed $definition,
    ): void {
        if (!\is_array($definition) || \array_is_list($definition)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertExactMapKeys(
            $definition,
            ['arguments', 'construction', 'id', 'shared', 'type'],
        );

        self::assertMapKeysSortedByByteOrder($definition);

        if (($definition['id'] ?? null) !== $serviceId) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (!\is_string($definition['id']) || !self::isSafeContainerId($definition['id'])) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        $type = $definition['type'];

        if (
            !\is_string($type)
            || (
                $type !== self::CONTAINER_SERVICE_TYPE_CLASS
                && $type !== self::CONTAINER_SERVICE_TYPE_FACTORY
            )
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (!\is_bool($definition['shared'])) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (!\is_array($definition['arguments']) || !\array_is_list($definition['arguments'])) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertContainerGraphValue($definition['arguments'], 0);

        if (!\is_array($definition['construction']) || \array_is_list($definition['construction'])) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertContainerGraphValue($definition['construction'], 0);

        self::validateContainerServiceConstruction(
            type: $type,
            construction: $definition['construction'],
        );
    }

    /**
     * @param array<string, mixed> $construction
     *
     * @throws ArtifactInvalidException
     */
    private static function validateContainerServiceConstruction(
        string $type,
        array $construction,
    ): void {
        self::assertMapKeysSortedByByteOrder($construction);

        if ($type === self::CONTAINER_SERVICE_TYPE_CLASS) {
            self::validateContainerClassConstruction($construction);

            return;
        }

        if ($type === self::CONTAINER_SERVICE_TYPE_FACTORY) {
            self::validateContainerFactoryConstruction($construction);

            return;
        }

        throw ArtifactInvalidException::withReason(
            ArtifactInvalidException::REASON_SCHEMA_INVALID,
        );
    }

    /**
     * @param array<string, mixed> $construction
     *
     * @throws ArtifactInvalidException
     */
    private static function validateContainerClassConstruction(array $construction): void
    {
        self::assertExactMapKeys($construction, ['class']);
        self::assertMapKeysSortedByByteOrder($construction);

        $class = $construction['class'] ?? null;

        if (!\is_string($class) || !self::isContainerClassLikeString($class)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }
    }

    /**
     * @param array<string, mixed> $construction
     *
     * @throws ArtifactInvalidException
     */
    private static function validateContainerFactoryConstruction(array $construction): void
    {
        self::assertExactMapKeys($construction, ['factory']);
        self::assertMapKeysSortedByByteOrder($construction);

        $factory = $construction['factory'] ?? null;

        if (!\is_array($factory) || \array_is_list($factory)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertMapKeysSortedByByteOrder($factory);

        $kind = $factory['kind'] ?? null;

        if ($kind === self::CONTAINER_FACTORY_CLASS_METHOD) {
            self::validateContainerFactoryClassMethod($factory);

            return;
        }

        if ($kind === self::CONTAINER_FACTORY_SERVICE_METHOD) {
            self::validateContainerFactoryServiceMethod($factory);

            return;
        }

        throw ArtifactInvalidException::withReason(
            ArtifactInvalidException::REASON_SCHEMA_INVALID,
        );
    }

    /**
     * @param array<string, mixed> $factory
     *
     * @throws ArtifactInvalidException
     */
    private static function validateContainerFactoryClassMethod(array $factory): void
    {
        self::assertExactMapKeys($factory, ['class', 'kind', 'method']);
        self::assertMapKeysSortedByByteOrder($factory);

        if (($factory['kind'] ?? null) !== self::CONTAINER_FACTORY_CLASS_METHOD) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (
            !\is_string($factory['class'] ?? null)
            || !self::isContainerClassLikeString($factory['class'])
            || !\is_string($factory['method'] ?? null)
            || \preg_match(self::CONTAINER_METHOD_PATTERN, $factory['method']) !== 1
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }
    }

    /**
     * @param array<string, mixed> $factory
     *
     * @throws ArtifactInvalidException
     */
    private static function validateContainerFactoryServiceMethod(array $factory): void
    {
        self::assertExactMapKeys($factory, ['kind', 'method', 'service']);
        self::assertMapKeysSortedByByteOrder($factory);

        if (($factory['kind'] ?? null) !== self::CONTAINER_FACTORY_SERVICE_METHOD) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (
            !\is_string($factory['service'] ?? null)
            || !self::isSafeContainerId($factory['service'])
            || !\is_string($factory['method'] ?? null)
            || \preg_match(self::CONTAINER_METHOD_PATTERN, $factory['method']) !== 1
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function validateContainerTags(mixed $tags): void
    {
        if (!self::isMapArray($tags)) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertMapKeysSortedByByteOrder($tags);

        foreach ($tags as $tag => $entries) {
            if (!\is_string($tag) || \preg_match(self::CONTAINER_TAG_PATTERN, $tag) !== 1) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (!\is_array($entries) || !\array_is_list($entries)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (\count($entries) > self::MAX_CONTAINER_LIST_ITEMS) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            $previousPriority = null;
            $previousId = null;
            $seen = [];

            foreach ($entries as $entry) {
                if (!\is_array($entry) || \array_is_list($entry)) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                self::assertExactMapKeys($entry, ['id', 'priority']);
                self::assertMapKeysSortedByByteOrder($entry);

                if (!\is_string($entry['id']) || !self::isSafeContainerId($entry['id'])) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                if (!\is_int($entry['priority'])) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                $id = $entry['id'];
                $priority = $entry['priority'];

                if (
                    $previousPriority !== null
                    && (
                        $priority > $previousPriority
                        || ($priority === $previousPriority && \strcmp($id, $previousId) < 0)
                    )
                ) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                if (isset($seen[$id])) {
                    throw ArtifactInvalidException::withReason(
                        ArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                $seen[$id] = true;
                $previousPriority = $priority;
                $previousId = $id;
            }
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function assertContainerGraphValue(mixed $value, int $depth): void
    {
        if ($depth > self::MAX_CONTAINER_GRAPH_DEPTH) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return;
        }

        if (\is_string($value)) {
            if (!self::isSafeContainerString($value)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            return;
        }

        if (\is_float($value) || \is_resource($value) || \is_object($value)) {
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
            self::assertContainerGraphList($value, $depth + 1);

            return;
        }

        self::assertContainerGraphMap($value, $depth + 1);
    }

    /**
     * @param list<mixed> $value
     *
     * @throws ArtifactInvalidException
     */
    private static function assertContainerGraphList(array $value, int $depth): void
    {
        if (\count($value) > self::MAX_CONTAINER_LIST_ITEMS) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::rejectContainerCallableLikeList($value);

        foreach ($value as $item) {
            self::assertContainerGraphValue($item, $depth);
        }
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @throws ArtifactInvalidException
     */
    private static function assertContainerGraphMap(array $value, int $depth): void
    {
        if (\count($value) > self::MAX_CONTAINER_MAP_KEYS) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        self::assertMapKeysSortedByByteOrder($value);

        foreach ($value as $key => $item) {
            if (!\is_string($key) || !self::isSafeContainerMapKey($key)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::assertContainerGraphValue($item, $depth);
        }
    }

    /**
     * @param list<mixed> $value
     *
     * @throws ArtifactInvalidException
     */
    private static function rejectContainerCallableLikeList(array $value): void
    {
        if (\count($value) !== 2) {
            return;
        }

        [$target, $method] = $value;

        if (!\is_string($target) || !\is_string($method)) {
            return;
        }

        if (
            self::isContainerClassLikeString($target)
            && \preg_match(self::CONTAINER_METHOD_PATTERN, $method) === 1
        ) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }
    }

    private static function isSafeContainerId(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= self::MAX_CONTAINER_STRING_BYTES
            && \trim($value) === $value
            && \preg_match('/\s/u', $value) !== 1
            && !self::containsUnsafeBytes($value)
            && !self::looksLikeAbsolutePath($value)
            && !\str_contains($value, '://')
            && !\str_contains($value, '::')
            && !self::looksLikeContainerSourceSnippet($value)
            && !self::looksLikeContainerEnvValue($value);
    }

    private static function isSafeContainerParameterName(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= self::MAX_CONTAINER_STRING_BYTES
            && \preg_match('/\A[A-Za-z_][A-Za-z0-9_.-]{0,255}\z/', $value) === 1
            && !self::containsUnsafeBytes($value)
            && !self::looksLikeAbsolutePath($value)
            && !\str_contains($value, '://')
            && !\str_contains($value, '::')
            && !self::looksLikeContainerSourceSnippet($value)
            && !self::looksLikeContainerEnvValue($value);
    }

    private static function isSafeContainerMapKey(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= self::MAX_CONTAINER_STRING_BYTES
            && \trim($value) === $value
            && !self::containsUnsafeBytes($value)
            && !self::looksLikeAbsolutePath($value)
            && !\str_contains($value, '://')
            && !\str_contains($value, '::')
            && !self::looksLikeContainerSourceSnippet($value)
            && !self::looksLikeContainerEnvValue($value);
    }

    private static function isSafeContainerString(string $value): bool
    {
        return \strlen($value) <= self::MAX_CONTAINER_STRING_BYTES
            && !self::containsUnsafeBytes($value)
            && !self::looksLikeAbsolutePath($value)
            && !\str_contains($value, '://')
            && !\str_contains($value, '::')
            && !self::looksLikeContainerSourceSnippet($value)
            && !self::looksLikeContainerEnvValue($value);
    }

    private static function isContainerClassLikeString(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= self::MAX_CONTAINER_STRING_BYTES
            && \trim($value) === $value
            && \preg_match(self::CONTAINER_CLASS_LIKE_PATTERN, $value) === 1
            && !self::containsUnsafeBytes($value)
            && !self::looksLikeAbsolutePath($value)
            && !\str_starts_with($value, '\\')
            && !\str_contains($value, '::')
            && !self::looksLikeContainerSourceSnippet($value);
    }

    private static function looksLikeContainerSourceSnippet(string $value): bool
    {
        return \preg_match(self::CONTAINER_SOURCE_SNIPPET_PATTERN, $value) === 1;
    }

    private static function looksLikeContainerEnvValue(string $value): bool
    {
        return \preg_match(self::CONTAINER_ENV_LIKE_PATTERN, $value) === 1;
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

    /**
     * @param array<array-key, mixed> $map
     *
     * @throws ArtifactInvalidException
     */
    private static function assertMapKeysSortedByByteOrder(array $map): void
    {
        $keys = \array_keys($map);

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                throw ArtifactInvalidException::withReason(
                    ArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }

        $sorted = $keys;
        \sort($sorted, \SORT_STRING);

        if ($keys !== $sorted) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }
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
