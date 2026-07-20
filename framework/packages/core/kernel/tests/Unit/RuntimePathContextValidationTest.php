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

use Coretsia\Kernel\Runtime\RuntimePathContext;
use PHPUnit\Framework\TestCase;

final class RuntimePathContextValidationTest extends TestCase
{
    public function testNormalizesRuntimePathsWithoutFilesystemAccess(): void
    {
        $context = new RuntimePathContext(
            skeletonRoot: '/path/that/does/not/exist/skeleton///',
            artifactRoot: '/path/that/does/not/exist/skeleton/var/cache///',
        );

        self::assertSame(
            '/path/that/does/not/exist/skeleton',
            $context->skeletonRoot(),
        );
        self::assertSame(
            '/path/that/does/not/exist/skeleton/var/cache',
            $context->artifactRoot(),
        );
    }

    public function testNormalizesWindowsSeparatorsAndPreservesFilesystemRoots(): void
    {
        $windowsContext = new RuntimePathContext(
            skeletonRoot: 'C:\\coretsia\\skeleton\\',
            artifactRoot: 'C:\\coretsia\\skeleton\\var\\cache\\',
        );

        self::assertSame(
            'C:/coretsia/skeleton',
            $windowsContext->skeletonRoot(),
        );
        self::assertSame(
            'C:/coretsia/skeleton/var/cache',
            $windowsContext->artifactRoot(),
        );

        $rootContext = new RuntimePathContext(
            skeletonRoot: '/',
            artifactRoot: 'D:/',
        );

        self::assertSame('/', $rootContext->skeletonRoot());
        self::assertSame('D:/', $rootContext->artifactRoot());
    }

    public function testRejectsInvalidSkeletonRootValuesDeterministically(): void
    {
        foreach (
            [
                '',
                ' /srv/coretsia',
                '/srv/coretsia ',
                "srv/coretsia\nroot",
                'https://example.test/coretsia',
            ] as $skeletonRoot
        ) {
            $exception = self::catchInvalidArgument(
                static fn (): RuntimePathContext => new RuntimePathContext(
                    skeletonRoot: $skeletonRoot,
                    artifactRoot: '/srv/coretsia/var/cache',
                ),
            );

            self::assertSame(
                'runtime-path-context-skeleton-root-invalid',
                $exception->getMessage(),
            );
        }
    }

    public function testRejectsInvalidArtifactRootValuesDeterministically(): void
    {
        foreach (
            [
                '',
                ' /srv/coretsia/var/cache',
                '/srv/coretsia/var/cache ',
                "srv/coretsia/var\tcache",
                'file://srv/coretsia/var/cache',
            ] as $artifactRoot
        ) {
            $exception = self::catchInvalidArgument(
                static fn (): RuntimePathContext => new RuntimePathContext(
                    skeletonRoot: '/srv/coretsia',
                    artifactRoot: $artifactRoot,
                ),
            );

            self::assertSame(
                'runtime-path-context-artifact-root-invalid',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @param callable(): RuntimePathContext $operation
     */
    private static function catchInvalidArgument(
        callable $operation,
    ): \InvalidArgumentException {
        try {
            $operation();
        } catch (\InvalidArgumentException $exception) {
            return $exception;
        }

        self::fail('Expected InvalidArgumentException was not thrown.');
    }
}
