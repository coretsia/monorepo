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

final class CacheVerifyDetectsArtifactByteDriftTest extends TestCase
{
    private string $skeletonRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skeletonRoot = ArtifactPipelineTestSupport::temporaryRoot('cache-verify-byte-drift');

        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig('raw-payload-secret-value'),
        );
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->skeletonRoot);

        parent::tearDown();
    }

    public function testVerifyIsCleanImmediatelyAfterCompile(): void
    {
        $result = ArtifactPipelineTestSupport::verifyArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
        );

        self::assertSame('clean', $result['outcome']);
        self::assertTrue($result['clean']);
        self::assertFalse($result['dirty']);
        self::assertFalse($result['invalid']);
    }

    public function testVerifyDetectsValidPhpByteDriftAsDirty(): void
    {
        self::assertSame(
            'clean',
            ArtifactPipelineTestSupport::verifyArtifacts($this, $this->skeletonRoot)['outcome'],
        );

        $path = ArtifactPipelineTestSupport::artifactPath($this->skeletonRoot, 'config.php');
        $bytes = \file_get_contents($path);

        self::assertIsString($bytes);
        self::assertStringContainsString("<?php\n\nreturn", $bytes);

        $mutated = \str_replace(
            "<?php\n\nreturn",
            "<?php\n\n/* deterministic-byte-drift */\nreturn",
            $bytes,
        );

        self::assertNotSame($bytes, $mutated);

        \file_put_contents($path, $mutated);

        $result = ArtifactPipelineTestSupport::verifyArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
        );

        self::assertSame('dirty', $result['outcome']);
        self::assertFalse($result['clean']);
        self::assertTrue($result['dirty']);
        self::assertFalse($result['invalid']);
        self::assertSame(1, $result['counts']['dirty_artifact_count']);

        $configResult = self::artifactResult($result, 'config.php');

        self::assertSame('dirty', $configResult['status']);
        self::assertSame('changed', $configResult['reason']);
    }

    public function testVerifyDetectsInvalidPhpSyntaxAsInvalid(): void
    {
        $path = ArtifactPipelineTestSupport::artifactPath($this->skeletonRoot, 'config.php');

        \file_put_contents($path, "<?php\nreturn [\n");

        $result = ArtifactPipelineTestSupport::verifyArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
        );

        self::assertSame('invalid', $result['outcome']);
        self::assertFalse($result['clean']);
        self::assertFalse($result['dirty']);
        self::assertTrue($result['invalid']);
        self::assertSame(1, $result['counts']['invalid_artifact_count']);

        $configResult = self::artifactResult($result, 'config.php');

        self::assertSame('invalid', $configResult['status']);
        self::assertSame('invalid', $configResult['reason']);
    }

    public function testVerifyExplainDoesNotLeakRawPayloadValuesOrAbsolutePaths(): void
    {
        $path = ArtifactPipelineTestSupport::artifactPath($this->skeletonRoot, 'config.php');
        $bytes = \file_get_contents($path);

        self::assertIsString($bytes);

        \file_put_contents(
            $path,
            \str_replace(
                "<?php\n\nreturn",
                "<?php\n\n/* deterministic-byte-drift */\nreturn",
                $bytes,
            ),
        );

        $result = ArtifactPipelineTestSupport::verifyArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
        );

        $encoded = \json_encode($result, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('raw-payload-secret-value', $encoded);
        self::assertStringNotContainsString($this->skeletonRoot, $encoded);
        self::assertStringNotContainsString(\sys_get_temp_dir(), $encoded);

        $configResult = self::artifactResult($result, 'config.php');

        self::assertSame(
            [
                [
                    'basename' => 'config.php',
                    'path' => 'var/cache/web/config.php',
                    'reason' => 'changed',
                ],
            ],
            $configResult['explain']['entries'],
        );
    }

    /**
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    private static function artifactResult(array $result, string $basename): array
    {
        foreach ($result['artifacts'] as $artifact) {
            if (($artifact['basename'] ?? null) === $basename) {
                self::assertIsArray($artifact);

                return $artifact;
            }
        }

        self::fail('Artifact result was not found: ' . $basename);
    }
}
