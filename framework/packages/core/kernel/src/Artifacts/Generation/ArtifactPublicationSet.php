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

namespace Coretsia\Kernel\Artifacts\Generation;

use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;

/**
 * Immutable in-memory publication set for one artifact generation.
 *
 * The set stores canonical bytes only. Constructor envelope inputs are used for
 * schema, identity, shared-fingerprint, and byte/envelope consistency checks and
 * are not retained as mutable publication state.
 *
 * @internal Kernel immutable artifact generation boundary.
 */
final readonly class ArtifactPublicationSet
{
    private ArtifactGenerationId $generationId;

    /**
     * @param array<int|string, mixed> $moduleManifestEnvelope
     * @param array<int|string, mixed> $configEnvelope
     * @param array<int|string, mixed> $containerEnvelope
     */
    public function __construct(
        array $moduleManifestEnvelope,
        private string $moduleManifestBytes,
        array $configEnvelope,
        private string $configBytes,
        array $containerEnvelope,
        private string $containerBytes,
    ) {
        $validator = new ArtifactSchemaValidator();

        $moduleManifestFingerprint = self::validatedFingerprint(
            validator: $validator,
            envelope: $moduleManifestEnvelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_MODULE_MANIFEST,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_MODULE_MANIFEST,
        );
        $configFingerprint = self::validatedFingerprint(
            validator: $validator,
            envelope: $configEnvelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONFIG,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONFIG,
        );
        $containerFingerprint = self::validatedFingerprint(
            validator: $validator,
            envelope: $containerEnvelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
        );

        if (
            $moduleManifestFingerprint !== $configFingerprint
            || $moduleManifestFingerprint !== $containerFingerprint
        ) {
            throw new \InvalidArgumentException('artifact-publication-set-fingerprint-mismatch');
        }

        $this->generationId = ArtifactGenerationId::fromString($moduleManifestFingerprint);

        self::assertCanonicalBytes(
            envelope: $moduleManifestEnvelope,
            bytes: $this->moduleManifestBytes,
        );
        self::assertCanonicalBytes(
            envelope: $configEnvelope,
            bytes: $this->configBytes,
        );
        self::assertCanonicalBytes(
            envelope: $containerEnvelope,
            bytes: $this->containerBytes,
        );
    }

    public function generationId(): ArtifactGenerationId
    {
        return $this->generationId;
    }

    public function fingerprint(): string
    {
        return $this->generationId->value();
    }

    public function moduleManifestBytes(): string
    {
        return $this->moduleManifestBytes;
    }

    public function configBytes(): string
    {
        return $this->configBytes;
    }

    public function containerBytes(): string
    {
        return $this->containerBytes;
    }

    /**
     * @return array{
     *     'config.php': string,
     *     'container.php': string,
     *     'module-manifest.php': string
     * }
     */
    public function artifactBytes(): array
    {
        return [
            ArtifactGeneration::CONFIG_BASENAME => $this->configBytes,
            ArtifactGeneration::CONTAINER_BASENAME => $this->containerBytes,
            ArtifactGeneration::MODULE_MANIFEST_BASENAME => $this->moduleManifestBytes,
        ];
    }

    /**
     * @param array<int|string, mixed> $envelope
     */
    private static function validatedFingerprint(
        ArtifactSchemaValidator $validator,
        array $envelope,
        string $expectedName,
        int $expectedSchemaVersion,
    ): string {
        try {
            $validator->validateExpected(
                envelope: $envelope,
                expectedName: $expectedName,
                expectedSchemaVersion: $expectedSchemaVersion,
            );
        } catch (\Throwable) {
            throw new \InvalidArgumentException('artifact-publication-set-envelope-invalid');
        }

        $header = $envelope['_meta'] ?? null;
        $fingerprint = \is_array($header)
            ? ($header['fingerprint'] ?? null)
            : null;

        if (!\is_string($fingerprint)) {
            throw new \InvalidArgumentException('artifact-publication-set-envelope-invalid');
        }

        return $fingerprint;
    }

    /**
     * @param array<int|string, mixed> $envelope
     */
    private static function assertCanonicalBytes(
        array $envelope,
        string $bytes,
    ): void {
        if ($bytes === '') {
            throw new \InvalidArgumentException('artifact-publication-set-bytes-invalid');
        }

        try {
            $expectedBytes = StablePhpArrayDumper::dumpStableEnvelope($envelope);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('artifact-publication-set-bytes-invalid');
        }

        if ($bytes !== $expectedBytes) {
            throw new \InvalidArgumentException('artifact-publication-set-bytes-invalid');
        }
    }
}
