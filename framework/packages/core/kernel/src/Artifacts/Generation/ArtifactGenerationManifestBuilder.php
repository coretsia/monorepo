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
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;

/**
 * Builds the Kernel-owned `artifact-generation@1` envelope from an immutable
 * in-memory publication set.
 *
 * @internal Kernel immutable artifact generation boundary.
 */
final readonly class ArtifactGenerationManifestBuilder
{
    public function __construct(
        private ArtifactEnvelopeFactory $envelopeFactory,
    ) {
    }

    /**
     * @return array{
     *     _meta: array<string, mixed>,
     *     payload: array<string, mixed>
     * }
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function build(
        ArtifactPublicationSet $publicationSet,
    ): array {
        return $this->envelopeFactory->artifactGeneration(
            fingerprint: $publicationSet->fingerprint(),
            payload: [
                'artifacts' => [
                    ArtifactGeneration::CONFIG_BASENAME => self::metadata(
                        $publicationSet->configBytes(),
                    ),
                    ArtifactGeneration::CONTAINER_BASENAME => self::metadata(
                        $publicationSet->containerBytes(),
                    ),
                    ArtifactGeneration::MODULE_MANIFEST_BASENAME => self::metadata(
                        $publicationSet->moduleManifestBytes(),
                    ),
                ],
                'generationId' => $publicationSet->fingerprint(),
                'schemaVersion' => ArtifactEnvelopeFactory::SCHEMA_VERSION_ARTIFACT_GENERATION,
            ],
        );
    }

    /**
     * @return array{bytes: int, sha256: string}
     */
    private static function metadata(string $bytes): array
    {
        return [
            'bytes' => \strlen($bytes),
            'sha256' => \hash('sha256', $bytes),
        ];
    }
}
