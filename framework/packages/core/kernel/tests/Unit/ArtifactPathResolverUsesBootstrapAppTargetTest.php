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

use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use PHPUnit\Framework\TestCase;

final class ArtifactPathResolverUsesBootstrapAppTargetTest extends TestCase
{
    public function testResolvesArtifactRootUnderDefaultAppTargetCacheDirectory(): void
    {
        $resolver = new ArtifactPathResolver();
        $bootstrapConfig = self::bootstrapConfig(
            appTarget: AppTarget::Api,
            artifactsCacheDir: 'var/cache',
        );

        self::assertSame(
            'var/cache/api',
            $resolver->relativeCacheDirectory($bootstrapConfig),
        );
        self::assertSame(
            '/workspace/skeleton/var/cache/api',
            $resolver->artifactRoot($bootstrapConfig),
        );
    }

    public function testResolvesArtifactRootUnderConfiguredCacheDirectory(): void
    {
        $resolver = new ArtifactPathResolver();
        $bootstrapConfig = self::bootstrapConfig(
            appTarget: AppTarget::Web,
            artifactsCacheDir: 'var/artifacts_cache',
        );

        self::assertSame(
            'var/artifacts_cache/web',
            $resolver->relativeCacheDirectory($bootstrapConfig),
        );
        self::assertSame(
            '/workspace/skeleton/var/artifacts_cache/web',
            $resolver->artifactRoot($bootstrapConfig),
        );
    }

    public function testEveryAppTargetIsAppendedExactlyOnceToArtifactRoot(): void
    {
        $resolver = new ArtifactPathResolver();
        $artifactsCacheDir = 'var/cache';

        foreach (AppTarget::cases() as $appTarget) {
            $bootstrapConfig = self::bootstrapConfig(
                appTarget: $appTarget,
                artifactsCacheDir: $artifactsCacheDir,
            );

            $relativeRoot =
                $artifactsCacheDir
                . '/'
                . $appTarget->value;

            self::assertSame(
                $relativeRoot,
                $resolver->relativeCacheDirectory($bootstrapConfig),
            );
            self::assertSame(
                '/workspace/skeleton/' . $relativeRoot,
                $resolver->artifactRoot($bootstrapConfig),
            );
        }
    }

    private static function bootstrapConfig(
        AppTarget $appTarget,
        string $artifactsCacheDir,
    ): BootstrapConfig {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: 'micro',
            debug: false,
            artifactsCacheDir: $artifactsCacheDir,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: $appTarget,
            skeletonRoot: '/workspace/skeleton',
        );
    }
}
