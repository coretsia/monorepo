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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Boot\Exception\ArtifactRuntimeBootException;
use PHPUnit\Framework\TestCase;

final class ArtifactRuntimeBootRejectsMixedGenerationTest extends TestCase
{
    public function testRejectsConfigArtifactCopiedFromAnotherGeneration(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'artifact-runtime-mixed-generation',
        );

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(
                    'generation-one',
                ),
            );

            $firstGeneration = ArtifactPipelineTestSupport::currentGeneration(
                $root,
            );

            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(
                    'generation-two',
                ),
            );

            $currentGeneration = ArtifactPipelineTestSupport::currentGeneration(
                $root,
            );

            self::assertNotSame(
                $firstGeneration->generationId()->value(),
                $currentGeneration->generationId()->value(),
            );
            self::assertDirectoryExists(
                $firstGeneration->generationDirectory(),
            );
            self::assertDirectoryExists(
                $currentGeneration->generationDirectory(),
            );

            $mixedConfigBytes = \file_get_contents(
                $firstGeneration->configPath(),
            );

            self::assertIsString($mixedConfigBytes);
            self::assertNotFalse(
                \file_put_contents(
                    $currentGeneration->configPath(),
                    $mixedConfigBytes,
                ),
            );

            self::updateGenerationManifestEntry(
                generation: $currentGeneration,
                basename: ArtifactGeneration::CONFIG_BASENAME,
                bytes: $mixedConfigBytes,
            );

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                    skeletonRoot: $root,
                );

                self::fail(
                    'Expected mixed-generation artifact rejection.',
                );
            } catch (ArtifactRuntimeBootException $exception) {
                self::assertGenerationInvalid($exception, $root);
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    private static function updateGenerationManifestEntry(
        ArtifactGeneration $generation,
        string $basename,
        string $bytes,
    ): void {
        $manifest = self::readPhpArray(
            $generation->generationManifestPath(),
        );
        $payload = $manifest['payload'] ?? null;

        self::assertIsArray($payload);

        $artifacts = $payload['artifacts'] ?? null;

        self::assertIsArray($artifacts);
        self::assertArrayHasKey($basename, $artifacts);

        $artifacts[$basename] = [
            'bytes' => \strlen($bytes),
            'sha256' => \hash('sha256', $bytes),
        ];
        $payload['artifacts'] = $artifacts;
        $manifest['payload'] = $payload;

        ArtifactPipelineTestSupport::writePhpReturn(
            path: $generation->generationManifestPath(),
            value: $manifest,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function readPhpArray(string $path): array
    {
        $value = require $path;

        self::assertIsArray($value);

        /** @var array<string, mixed> $value */
        return $value;
    }

    private static function assertGenerationInvalid(
        ArtifactRuntimeBootException $exception,
        string $root,
    ): void {
        self::assertSame(
            ArtifactRuntimeBootException::ERROR_CODE,
            $exception->errorCode(),
        );
        self::assertSame(
            ArtifactRuntimeBootException::REASON_GENERATION_INVALID,
            $exception->reason(),
        );
        self::assertSame(
            ArtifactRuntimeBootException::ERROR_CODE
            . ': '
            . ArtifactRuntimeBootException::REASON_GENERATION_INVALID,
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            $root,
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            'generation-one',
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            'generation-two',
            $exception->getMessage(),
        );
    }
}
