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

final class CacheVerifierInvalidGenerationProjectionDeterminismTest extends TestCase
{
    public function testInvalidGenerationProjectionDoesNotDependOnMutationOrder(): void
    {
        $firstRoot = ArtifactPipelineTestSupport::temporaryRoot('cache-verifier-order-a');
        $secondRoot = ArtifactPipelineTestSupport::temporaryRoot('cache-verifier-order-b');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $firstRoot,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $secondRoot,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            self::invalidateGeneration(
                root: $firstRoot,
                mutationOrder: [
                    'config.php',
                    'container.php',
                ],
            );

            self::invalidateGeneration(
                root: $secondRoot,
                mutationOrder: [
                    'container.php',
                    'config.php',
                ],
            );

            $first = ArtifactPipelineTestSupport::verifyArtifacts(
                testCase: $this,
                skeletonRoot: $firstRoot,
            );

            $second = ArtifactPipelineTestSupport::verifyArtifacts(
                testCase: $this,
                skeletonRoot: $secondRoot,
            );

            self::assertSame($first, $second);
            self::assertSame('invalid', $first['outcome']);

            self::assertSame(
                [
                    'config.php',
                    'container.php',
                    'generation-manifest.php',
                    'module-manifest.php',
                ],
                \array_column(
                    $first['artifacts'],
                    'basename',
                ),
            );

            self::assertSame(
                [
                    'invalid',
                    'invalid',
                    'invalid',
                    'invalid',
                ],
                \array_column(
                    $first['artifacts'],
                    'reason',
                ),
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($firstRoot);
            ArtifactPipelineTestSupport::removeTree($secondRoot);
        }
    }

    /**
     * @param list<'config.php'|'container.php'> $mutationOrder
     */
    private static function invalidateGeneration(
        string $root,
        array $mutationOrder,
    ): void {
        $paths = ArtifactPipelineTestSupport::currentArtifactPaths($root);

        foreach ($mutationOrder as $basename) {
            $path = $paths[$basename] ?? null;

            self::assertIsString($path);

            if ($basename === 'config.php') {
                $bytes = "<?php\n\nreturn [];\n";

                self::assertSame(
                    \strlen($bytes),
                    \file_put_contents($path, $bytes),
                );

                continue;
            }

            self::assertSame('container.php', $basename);
            self::assertTrue(\unlink($path));
        }
    }
}
