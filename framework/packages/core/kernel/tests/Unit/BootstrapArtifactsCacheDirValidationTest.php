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

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BootstrapArtifactsCacheDirValidationTest extends TestCase
{
    #[DataProvider('validArtifactsCacheDirProvider')]
    public function testBootstrapInputsAcceptValidArtifactsCacheDirectories(string $path): void
    {
        $input = new BootstrapInput(
            skeletonRoot: '/workspace/skeleton',
            appTarget: AppTarget::Web,
            artifactsCacheDir: $path,
        );

        $config = new BootstrapConfig(
            appEnv: 'local',
            preset: 'micro',
            debug: false,
            artifactsCacheDir: $path,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: '/workspace/skeleton',
        );

        self::assertSame($path, $input->artifactsCacheDir());
        self::assertSame($path, $config->artifactsCacheDir());
    }

    #[DataProvider('invalidArtifactsCacheDirProvider')]
    public function testBootstrapInputRejectsInvalidArtifactsCacheDirectory(string $path): void
    {
        try {
            new BootstrapInput(
                skeletonRoot: '/workspace/skeleton',
                appTarget: AppTarget::Web,
                artifactsCacheDir: $path,
            );
        } catch (BootstrapException $exception) {
            self::assertSame(
                BootstrapException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                BootstrapException::REASON_ARTIFACTS_CACHE_DIR_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_BOOTSTRAP_FAILED: bootstrap-artifacts-cache-dir-invalid',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $path,
                $exception->getMessage(),
            );

            return;
        }

        self::fail('BootstrapInput must reject an invalid artifacts cache directory.');
    }

    #[DataProvider('invalidArtifactsCacheDirProvider')]
    public function testBootstrapConfigRejectsInvalidArtifactsCacheDirectory(string $path): void
    {
        try {
            new BootstrapConfig(
                appEnv: 'local',
                preset: 'micro',
                debug: false,
                artifactsCacheDir: $path,
                envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
                appTarget: AppTarget::Web,
                skeletonRoot: '/workspace/skeleton',
            );
        } catch (BootstrapException $exception) {
            self::assertSame(
                BootstrapException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                BootstrapException::REASON_ARTIFACTS_CACHE_DIR_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_BOOTSTRAP_FAILED: bootstrap-artifacts-cache-dir-invalid',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $path,
                $exception->getMessage(),
            );

            return;
        }

        self::fail('BootstrapConfig must reject an invalid artifacts cache directory.');
    }

    /**
     * @return iterable<string, array{0:non-empty-string}>
     */
    public static function validArtifactsCacheDirProvider(): iterable
    {
        yield 'default-var-cache' => [
            'var/cache',
        ];

        yield 'custom-var-artifacts-cache' => [
            'var/artifacts_cache',
        ];

        yield 'storage-coretsia-artifacts' => [
            'storage/coretsia/artifacts',
        ];

        yield 'maximum-supported-length' => [
            \str_repeat('a', 480),
        ];
    }

    /**
     * @return iterable<string, array{0:non-empty-string}>
     */
    public static function invalidArtifactsCacheDirProvider(): iterable
    {
        yield 'absolute-unix-path' => [
            '/cache',
        ];

        yield 'parent-relative-path' => [
            '../cache',
        ];

        yield 'embedded-parent-segment' => [
            'var/../cache',
        ];

        yield 'double-slash' => [
            'var//cache',
        ];

        yield 'skeleton-root-name' => [
            'skeleton',
        ];

        yield 'skeleton-prefixed-path' => [
            'skeleton/var/cache',
        ];

        yield 'absolute-windows-path' => [
            'C:\\cache',
        ];

        yield 'backslash-path' => [
            'var\\cache',
        ];

        yield 'too-long' => [
            \str_repeat('a', 481),
        ];

        yield 'bootstrap-config-root' => [
            'config/artifacts',
        ];

        yield 'application-root' => [
            'apps/artifacts',
        ];

        yield 'public-web-root' => [
            'public/cache',
        ];

        yield 'dependency-root' => [
            'vendor/generated',
        ];

        yield 'case-insensitive-skeleton-root' => [
            'Skeleton/var/cache',
        ];

        yield 'windows-con-device-name' => [
            'CON/cache',
        ];

        yield 'windows-nul-device-name-with-extension' => [
            'var/nul.txt',
        ];

        yield 'windows-trailing-dot' => [
            'var/cache.',
        ];

        yield 'windows-forbidden-question-mark' => [
            'var/ca?che',
        ];

        yield 'windows-forbidden-pipe' => [
            'var/ca|che',
        ];
    }
}
