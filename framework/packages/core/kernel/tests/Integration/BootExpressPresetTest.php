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

use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Tests\Support\AppBuilder;
use PHPUnit\Framework\TestCase;

final class BootExpressPresetTest extends TestCase
{
    public function testExpressPresetFailsDeterministicallyUntilPlatformHttpExists(): void
    {
        $result = AppBuilder::bootExpressExpectingRequiredMissing($this);

        try {
            $skeletonRoot = $result->skeletonRoot();
            $artifactPaths = $result->artifactPaths();
            $exception = $result->exception();

            self::assertFalse(
                \is_file($skeletonRoot . '/config/modes/express.php'),
                'express skeleton preset override fixture must not exist',
            );

            self::assertInstanceOf(ModuleRequiredMissingException::class, $exception);

            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_REQUIRED_MISSING,
                $exception->errorCode(),
            );

            self::assertSame(
                ModuleRequiredMissingException::REASON_PRESET_REQUIRED_MODULE_MISSING,
                $exception->reason(),
            );

            self::assertSame(
                'express',
                $exception->context()['preset'] ?? null,
            );

            self::assertSame(
                'platform.http',
                $exception->context()['missingModuleId'] ?? null,
            );

            self::assertArrayHasKey('container.php', $artifactPaths);

            self::assertFalse(
                \is_file($artifactPaths['container.php']),
                'container.php artifact must not be required after expected pre-boot failure',
            );
        } finally {
            AppBuilder::removeTree($result->skeletonRoot());
        }
    }
}
