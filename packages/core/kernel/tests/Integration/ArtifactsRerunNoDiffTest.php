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

use PHPUnit\Framework\TestCase;

final class ArtifactsRerunNoDiffTest extends TestCase
{
    private string $applicationRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationRoot = ArtifactPipelineTestSupport::temporaryRoot('artifacts-rerun-no-diff');
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->applicationRoot);

        parent::tearDown();
    }

    public function testArtifactCompileRerunProducesIdenticalBytes(): void
    {
        $firstResult = ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            applicationRoot: $this->applicationRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        $firstBytes = ArtifactPipelineTestSupport::artifactBytes($this->applicationRoot);

        $secondResult = ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            applicationRoot: $this->applicationRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        $secondBytes = ArtifactPipelineTestSupport::artifactBytes($this->applicationRoot);

        self::assertCompileResult($firstResult);
        self::assertCompileResult($secondResult);
        self::assertSame($firstResult, $secondResult);
        self::assertSame($firstBytes, $secondBytes);
    }

    public function testArtifactCompileWritesOnlyKernelOwnedGenerationFiles(): void
    {
        $result = ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            applicationRoot: $this->applicationRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        self::assertCompileResult($result);

        self::assertSame(
            [
                'config.php',
                'container.php',
                'generation-manifest.php',
                'module-manifest.php',
            ],
            \array_keys(ArtifactPipelineTestSupport::artifactBytes($this->applicationRoot)),
        );

        self::assertFileDoesNotExist($this->applicationRoot . '/var/cache/web/config.php');
        self::assertFileDoesNotExist($this->applicationRoot . '/var/cache/web/container.php');
        self::assertFileDoesNotExist($this->applicationRoot . '/var/cache/web/module-manifest.php');
        self::assertFileDoesNotExist($this->applicationRoot . '/var/cache/web/generation-manifest.php');
        self::assertFileDoesNotExist($this->applicationRoot . '/var/cache/web/routes.php');
    }

    /**
     * @param array<string,mixed> $result
     */
    private static function assertCompileResult(array $result): void
    {
        self::assertSame(
            [
                'schemaVersion',
                'generationId',
                'artifacts',
            ],
            \array_keys($result),
        );
        self::assertSame(1, $result['schemaVersion']);
        self::assertIsString($result['generationId']);
        self::assertMatchesRegularExpression(
            '/\A[a-f0-9]{64}\z/',
            $result['generationId'],
        );
        self::assertSame(
            [
                [
                    'identity' => 'module-manifest@1',
                    'basename' => 'module-manifest.php',
                ],
                [
                    'identity' => 'config@1',
                    'basename' => 'config.php',
                ],
                [
                    'identity' => 'container@1',
                    'basename' => 'container.php',
                ],
                [
                    'identity' => 'artifact-generation@1',
                    'basename' => 'generation-manifest.php',
                ],
            ],
            $result['artifacts'],
        );
    }
}
