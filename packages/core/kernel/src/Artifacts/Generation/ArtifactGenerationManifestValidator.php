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
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;

/**
 * Dedicated validation boundary for `artifact-generation@1` envelopes.
 *
 * Exact envelope, payload, artifact-name, byte-count, hash, generation-id, and
 * no-extra-key validation is delegated to ArtifactSchemaValidator so existing
 * and generation-specific artifact validation share one canonical schema law.
 *
 * @internal Kernel immutable artifact generation boundary.
 */
final readonly class ArtifactGenerationManifestValidator
{
    public function __construct(
        private ArtifactSchemaValidator $schemaValidator = new ArtifactSchemaValidator(),
    ) {
    }

    /**
     * @param array<int|string, mixed> $envelope
     *
     * @throws ArtifactInvalidException
     */
    public function validate(array $envelope): void
    {
        $this->schemaValidator->validateExpected(
            envelope: $envelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_GENERATION,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_ARTIFACT_GENERATION,
        );
    }
}
