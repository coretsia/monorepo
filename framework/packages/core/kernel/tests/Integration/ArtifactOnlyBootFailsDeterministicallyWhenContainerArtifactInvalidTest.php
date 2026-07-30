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

final class ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactInvalidTest extends TestCase
{
    public function testArtifactOnlyBootRejectsLegacyStubAsInvalidGeneration(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'legacy-stub-container-artifact',
        );

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $generation = ArtifactPipelineTestSupport::currentGeneration($root);
            $containerPath = $generation->containerPath();
            $envelope = self::readPhpArray($containerPath);

            $envelope['payload'] = [
                'compiled' => false,
                'kind' => 'stub',
            ];

            self::rewriteContainerEnvelope(
                generation: $generation,
                envelope: $envelope,
            );

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                    skeletonRoot: $root,
                );

                self::fail('Expected invalid artifact generation failure.');
            } catch (ArtifactRuntimeBootException $exception) {
                self::assertArtifactRuntimeFailure(
                    exception: $exception,
                    expectedReason: ArtifactRuntimeBootException::REASON_GENERATION_INVALID,
                    containerPath: $containerPath,
                );

                self::assertStringNotContainsString(
                    'stub',
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    'compiled',
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    'false',
                    $exception->getMessage(),
                );
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    public function testArtifactOnlyBootMapsGenerationValidContainerGraphFailureToPublicContainerReason(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'invalid-container-artifact-graph',
        );

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $generation = ArtifactPipelineTestSupport::currentGeneration($root);
            $containerPath = $generation->containerPath();
            $envelope = self::readPhpArray($containerPath);
            $payload = $envelope['payload'] ?? null;

            self::assertIsArray($payload);

            $aliases = $payload['aliases'] ?? null;

            self::assertIsArray($aliases);

            /*
             * This remains schema-valid and generation-valid, but the alias
             * target is absent from the compiled service graph. The failure is
             * therefore selected by CompiledContainerFactory, not by generation
             * validation.
             */
            $aliases['Coretsia\Tests\InvalidAlias'] = 'Coretsia\Tests\MissingService';

            \ksort($aliases, \SORT_STRING);

            $payload['aliases'] = $aliases;
            $envelope['payload'] = $payload;

            self::rewriteContainerEnvelope(
                generation: $generation,
                envelope: $envelope,
            );

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                    skeletonRoot: $root,
                );

                self::fail('Expected invalid compiled-container graph failure.');
            } catch (ArtifactRuntimeBootException $exception) {
                self::assertArtifactRuntimeFailure(
                    exception: $exception,
                    expectedReason: ArtifactRuntimeBootException::REASON_CONTAINER_ARTIFACT_INVALID,
                    containerPath: $containerPath,
                );

                self::assertStringNotContainsString(
                    'InvalidAlias',
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    'MissingService',
                    $exception->getMessage(),
                );
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    public function testArtifactOnlyBootRejectsContainerEvaluationFailureWithoutLeakingWarningText(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'invalid-container-artifact-warning',
        );

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $generation = ArtifactPipelineTestSupport::currentGeneration($root);
            $containerPath = $generation->containerPath();
            $secretPath = $root . '/secret/source.php';

            self::rewriteContainerBytes(
                generation: $generation,
                bytes: "<?php\n\n"
                . "trigger_error('failed to open stream: "
                . \addslashes($secretPath)
                . "', E_USER_WARNING);\n\n"
                . "return [];\n",
            );

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                    skeletonRoot: $root,
                );

                self::fail('Expected invalid artifact generation failure.');
            } catch (ArtifactRuntimeBootException $exception) {
                self::assertArtifactRuntimeFailure(
                    exception: $exception,
                    expectedReason: ArtifactRuntimeBootException::REASON_GENERATION_INVALID,
                    containerPath: $containerPath,
                );

                self::assertStringNotContainsString(
                    $secretPath,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    'failed to open stream',
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    'trigger_error',
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    'E_USER_WARNING',
                    $exception->getMessage(),
                );
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private static function rewriteContainerEnvelope(
        ArtifactGeneration $generation,
        array $envelope,
    ): void {
        ArtifactPipelineTestSupport::writePhpReturn(
            path: $generation->containerPath(),
            value: $envelope,
        );

        self::updateContainerManifestMetadata($generation);
    }

    private static function rewriteContainerBytes(
        ArtifactGeneration $generation,
        string $bytes,
    ): void {
        self::assertNotFalse(
            \file_put_contents(
                $generation->containerPath(),
                $bytes,
            ),
        );

        self::updateContainerManifestMetadata($generation);
    }

    private static function updateContainerManifestMetadata(
        ArtifactGeneration $generation,
    ): void {
        $containerBytes = \file_get_contents(
            $generation->containerPath(),
        );

        self::assertIsString($containerBytes);

        $manifest = self::readPhpArray(
            $generation->generationManifestPath(),
        );
        $payload = $manifest['payload'] ?? null;

        self::assertIsArray($payload);

        $artifacts = $payload['artifacts'] ?? null;

        self::assertIsArray($artifacts);

        $artifacts[ArtifactGeneration::CONTAINER_BASENAME] = [
            'bytes' => \strlen($containerBytes),
            'sha256' => \hash('sha256', $containerBytes),
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

    private static function assertArtifactRuntimeFailure(
        ArtifactRuntimeBootException $exception,
        string $expectedReason,
        string $containerPath,
    ): void {
        self::assertSame(
            ArtifactRuntimeBootException::ERROR_CODE,
            $exception->errorCode(),
        );
        self::assertSame(
            $expectedReason,
            $exception->reason(),
        );
        self::assertSame(
            ArtifactRuntimeBootException::ERROR_CODE
            . ': '
            . $expectedReason,
            $exception->getMessage(),
        );

        self::assertStringNotContainsString(
            $containerPath,
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            \dirname($containerPath),
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            \sys_get_temp_dir(),
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            'payload',
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            'stack trace',
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            'Throwable',
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            'Exception',
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            'Warning',
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            'warning',
            $exception->getMessage(),
        );
    }
}
