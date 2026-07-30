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

final class ArtifactRuntimeBootRejectsEnvelopeFingerprintMismatchTest extends TestCase
{
    public function testRejectsEnvelopeFingerprintDifferentFromGenerationId(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'artifact-runtime-envelope-fingerprint-mismatch',
        );

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $generation = ArtifactPipelineTestSupport::currentGeneration($root);
            $configPath = $generation->configPath();
            $envelope = self::readPhpArray($configPath);
            $meta = $envelope['_meta'] ?? null;

            self::assertIsArray($meta);

            $generationId = $generation->generationId()->value();
            $mismatchedFingerprint = \str_repeat('f', 64);

            if ($mismatchedFingerprint === $generationId) {
                $mismatchedFingerprint = \str_repeat('e', 64);
            }

            $meta['fingerprint'] = $mismatchedFingerprint;
            $envelope['_meta'] = $meta;

            ArtifactPipelineTestSupport::writePhpReturn(
                path: $configPath,
                value: $envelope,
            );

            $configBytes = \file_get_contents($configPath);

            self::assertIsString($configBytes);

            self::updateGenerationManifestEntry(
                generation: $generation,
                basename: ArtifactGeneration::CONFIG_BASENAME,
                bytes: $configBytes,
            );

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts(
                    skeletonRoot: $root,
                );

                self::fail(
                    'Expected envelope fingerprint mismatch rejection.',
                );
            } catch (ArtifactRuntimeBootException $exception) {
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
                    $generationId,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    $mismatchedFingerprint,
                    $exception->getMessage(),
                );
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
}
