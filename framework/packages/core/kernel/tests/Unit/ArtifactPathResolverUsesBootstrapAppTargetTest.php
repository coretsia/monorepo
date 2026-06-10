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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\Artifacts\Exception\ArtifactPathInvalidException;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use PHPUnit\Framework\TestCase;

final class ArtifactPathResolverUsesBootstrapAppTargetTest extends TestCase
{
    public function testResolvesPathsUnderAppTargetCacheDirectory(): void
    {
        $resolver = new ArtifactPathResolver();
        $bootstrapConfig = self::bootstrapConfig(AppTarget::Api);

        self::assertSame(
            'var/cache/api',
            $resolver->relativeCacheDirectory($bootstrapConfig, self::kernelConfig()),
        );

        self::assertSame(
            'var/cache/api/config.php',
            $resolver->relativePath(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: self::kernelConfig(),
                basename: ArtifactPathResolver::CONFIG_BASENAME,
            ),
        );

        self::assertSame(
            '/workspace/skeleton/var/cache/api/config.php',
            $resolver->resolve(
                bootstrapConfig: $bootstrapConfig,
                kernelConfig: self::kernelConfig(),
                basename: ArtifactPathResolver::CONFIG_BASENAME,
            ),
        );
    }

    public function testRejectsAbsoluteCacheDirWithoutLeakingConfiguredPath(): void
    {
        self::assertPathInvalid(
            kernelConfig: self::kernelConfig('/tmp/cache'),
            expectedReason: ArtifactPathInvalidException::REASON_CACHE_DIR_ABSOLUTE,
            forbiddenNeedles: [
                '/tmp/cache',
                '/workspace/skeleton',
            ],
        );
    }

    public function testRejectsTraversalCacheDirWithoutLeakingConfiguredPath(): void
    {
        self::assertPathInvalid(
            kernelConfig: self::kernelConfig('var/../cache'),
            expectedReason: ArtifactPathInvalidException::REASON_CACHE_DIR_TRAVERSAL,
            forbiddenNeedles: [
                'var/../cache',
                '/workspace/skeleton',
            ],
        );
    }

    public function testRejectsSkeletonPrefixedCacheDirWithoutLeakingConfiguredPath(): void
    {
        self::assertPathInvalid(
            kernelConfig: self::kernelConfig('skeleton/var/cache'),
            expectedReason: ArtifactPathInvalidException::REASON_CACHE_DIR_SKELETON_PREFIXED,
            forbiddenNeedles: [
                'skeleton/var/cache',
                '/workspace/skeleton',
            ],
        );
    }

    public function testRejectsInvalidCacheDirWithSafeReasonOnly(): void
    {
        self::assertPathInvalid(
            kernelConfig: self::kernelConfig('var/cache//web'),
            expectedReason: ArtifactPathInvalidException::REASON_CACHE_DIR_INVALID,
            forbiddenNeedles: [
                'var/cache//web',
                '/workspace/skeleton',
            ],
        );
    }

    /**
     * @param array<string, mixed> $kernelConfig
     * @param list<string> $forbiddenNeedles
     */
    private static function assertPathInvalid(
        array $kernelConfig,
        string $expectedReason,
        array $forbiddenNeedles,
    ): void {
        $resolver = new ArtifactPathResolver();

        try {
            $resolver->relativePath(
                bootstrapConfig: self::bootstrapConfig(AppTarget::Web),
                kernelConfig: $kernelConfig,
                basename: ArtifactPathResolver::CONFIG_BASENAME,
            );

            self::fail('Expected ArtifactPathInvalidException was not thrown.');
        } catch (ArtifactPathInvalidException $exception) {
            self::assertSame(ArtifactPathInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame($expectedReason, $exception->reason());
            self::assertStringContainsString($expectedReason, $exception->getMessage());

            foreach ($forbiddenNeedles as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $exception->getMessage(),
                    'Artifact path diagnostics must not leak configured paths or resolved absolute paths.',
                );
            }
        }
    }

    private static function bootstrapConfig(AppTarget $appTarget): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: 'micro',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: $appTarget,
            skeletonRoot: '/workspace/skeleton',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function kernelConfig(string $cacheDir = 'var/cache'): array
    {
        return [
            'artifacts' => [
                'cache_dir' => $cacheDir,
            ],
        ];
    }
}
