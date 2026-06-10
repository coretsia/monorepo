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

namespace Coretsia\Kernel\Artifacts;

use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\Header\ArtifactHeader;

/**
 * Kernel-owned canonical artifact envelope factory.
 *
 * This is the only Kernel-owned service that assembles artifact envelopes:
 *
 *     [
 *         '_meta' => <canonical header>,
 *         'payload' => <schema-specific payload>,
 *     ]
 *
 * It creates a fresh ArtifactHeader per artifact and normalizes payload/header
 * data into deterministic json-like array data suitable for:
 *
 * - stable PHP artifact emission;
 * - byte-level cache verification;
 * - artifact schema validation.
 *
 * This factory intentionally does not:
 *
 * - write files;
 * - read files;
 * - dump PHP bytes;
 * - calculate fingerprints;
 * - validate artifact-specific payload schemas;
 * - keep current artifact mutable state;
 * - include timestamps, absolute paths, tool versions, hostnames, user names,
 *   process ids, random bytes, or environment-specific bytes.
 *
 * Artifact-specific payload validation belongs to ArtifactSchemaValidator.
 * PHP byte emission belongs to StablePhpArrayDumper.
 *
 * @internal
 */
final readonly class ArtifactEnvelopeFactory
{
    public const string ARTIFACT_MODULE_MANIFEST = 'module-manifest';
    public const string ARTIFACT_CONFIG = 'config';
    public const string ARTIFACT_CONTAINER = 'container';

    public const int SCHEMA_VERSION_MODULE_MANIFEST = 1;
    public const int SCHEMA_VERSION_CONFIG = 1;
    public const int SCHEMA_VERSION_CONTAINER = 1;

    private const string GENERATOR = 'core/kernel/artifacts';

    public function __construct(
        private PayloadNormalizer $payloadNormalizer = new PayloadNormalizer(),
    ) {
    }

    /**
     * Creates a `module-manifest@1` canonical artifact envelope.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $requires
     *
     * @return array{_meta: array<string, mixed>, payload: array<string, mixed>}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function moduleManifest(
        string $fingerprint,
        array $payload,
        ?array $requires = null,
    ): array {
        return $this->createEnvelope(
            name: self::ARTIFACT_MODULE_MANIFEST,
            schemaVersion: self::SCHEMA_VERSION_MODULE_MANIFEST,
            fingerprint: $fingerprint,
            payload: $payload,
            requires: $requires,
        );
    }

    /**
     * Creates a `config@1` canonical artifact envelope.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $requires
     *
     * @return array{_meta: array<string, mixed>, payload: array<string, mixed>}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function config(
        string $fingerprint,
        array $payload,
        ?array $requires = null,
    ): array {
        return $this->createEnvelope(
            name: self::ARTIFACT_CONFIG,
            schemaVersion: self::SCHEMA_VERSION_CONFIG,
            fingerprint: $fingerprint,
            payload: $payload,
            requires: $requires,
        );
    }

    /**
     * Creates a `container@1` canonical artifact envelope.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $requires
     *
     * @return array{_meta: array<string, mixed>, payload: array<string, mixed>}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function container(
        string $fingerprint,
        array $payload,
        ?array $requires = null,
    ): array {
        return $this->createEnvelope(
            name: self::ARTIFACT_CONTAINER,
            schemaVersion: self::SCHEMA_VERSION_CONTAINER,
            fingerprint: $fingerprint,
            payload: $payload,
            requires: $requires,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $requires
     *
     * @return array{_meta: array<string, mixed>, payload: array<string, mixed>}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private function createEnvelope(
        string $name,
        int $schemaVersion,
        string $fingerprint,
        array $payload,
        ?array $requires,
    ): array {
        self::assertKnownKernelArtifact($name, $schemaVersion);

        $header = new ArtifactHeader(
            name: $name,
            schemaVersion: $schemaVersion,
            fingerprint: $fingerprint,
            generator: self::GENERATOR,
            requires: $requires,
        );

        $normalizedPayload = $this->payloadNormalizer->normalizeMap($payload, 'payload');

        $envelope = [
            '_meta' => $header->toArray(),
            'payload' => $normalizedPayload,
        ];

        $normalizedEnvelope = $this->payloadNormalizer->normalizeMap($envelope, 'artifact');

        self::assertCanonicalEnvelopeShape($normalizedEnvelope);

        /** @var array{_meta: array<string, mixed>, payload: array<string, mixed>} $normalizedEnvelope */
        return $normalizedEnvelope;
    }

    private static function assertKnownKernelArtifact(string $name, int $schemaVersion): void
    {
        $expectedSchemaVersion = match ($name) {
            self::ARTIFACT_MODULE_MANIFEST => self::SCHEMA_VERSION_MODULE_MANIFEST,
            self::ARTIFACT_CONFIG => self::SCHEMA_VERSION_CONFIG,
            self::ARTIFACT_CONTAINER => self::SCHEMA_VERSION_CONTAINER,
            default => null,
        };

        if ($expectedSchemaVersion === null) {
            throw new \InvalidArgumentException('artifact-envelope-name-invalid');
        }

        if ($schemaVersion !== $expectedSchemaVersion) {
            throw new \InvalidArgumentException('artifact-envelope-schema-version-invalid');
        }
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private static function assertCanonicalEnvelopeShape(array $envelope): void
    {
        $keys = \array_keys($envelope);

        if ($keys !== ['_meta', 'payload']) {
            throw ArtifactPayloadInvalidException::atPath(
                'artifact',
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        if (!\is_array($envelope['_meta']) || \array_is_list($envelope['_meta'])) {
            throw ArtifactPayloadInvalidException::atPath(
                'artifact._meta',
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        if (!\is_array($envelope['payload']) || \array_is_list($envelope['payload'])) {
            throw ArtifactPayloadInvalidException::atPath(
                'artifact.payload',
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }
    }
}
