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

use Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;

/**
 * Resolves and validates the current immutable artifact generation.
 *
 * @internal Kernel atomic artifact generation publication boundary.
 */
final readonly class ArtifactGenerationLocator
{
    private const string POINTER_PATTERN = '/\A([a-f0-9]{64})\n\z/';

    public function __construct(
        private ArtifactGenerationLock $lock,
        private ArtifactGenerationPathResolver $pathResolver,
        private ArtifactGenerationValidator $validator,
    ) {
    }

    /**
     * @throws ArtifactInvalidException
     * @throws ArtifactGenerationPublishException when shared lock acquisition or
     *                                            release fails.
     */
    public function locate(string $artifactRoot): ?ArtifactGeneration
    {
        return $this->lock->shared(
            $artifactRoot,
            function () use ($artifactRoot): ?ArtifactGeneration {
                $currentPath = $this->pathResolver->currentPath($artifactRoot);

                if (@\is_link($currentPath)) {
                    throw self::invalid();
                }

                if (!@\file_exists($currentPath)) {
                    return null;
                }

                if (
                    !@\is_file($currentPath)
                    || !@\is_readable($currentPath)
                ) {
                    throw self::invalid();
                }

                $pointer = @\file_get_contents($currentPath);

                if (!\is_string($pointer)) {
                    throw self::invalid();
                }

                $matches = [];

                if (
                    \preg_match(
                        self::POINTER_PATTERN,
                        $pointer,
                        $matches
                    ) !== 1
                ) {
                    throw self::invalid();
                }

                $generationId = ArtifactGenerationId::fromString($matches[1]);
                $generation = $this->pathResolver->generation(
                    artifactRoot: $artifactRoot,
                    generationId: $generationId,
                );

                $this->validator->validate($generation);

                return $generation;
            },
        );
    }

    private static function invalid(): ArtifactInvalidException
    {
        return ArtifactInvalidException::withReason(
            ArtifactInvalidException::REASON_INVALID,
        );
    }
}
