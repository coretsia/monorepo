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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Filesystem\ScopedFileLock;
use PHPUnit\Framework\TestCase;

final class ScopedFileLockOpenModeContractTest extends TestCase
{
    public function testUsesPlatformAwareCloseOnExecModeAndStablePermissions(): void
    {
        $path = new \ReflectionClass(ScopedFileLock::class)->getFileName();
        self::assertIsString($path);
        $source = \file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('private const int DIRECTORY_PERMISSIONS = 0775;', $source);
        self::assertStringContainsString('private const int FILE_PERMISSIONS = 0644;', $source);
        self::assertStringContainsString("? 'c+b'", $source);
        self::assertStringContainsString(": 'c+be'", $source);
        self::assertStringContainsString('self::openMode()', $source);
        self::assertStringContainsString(
            '@\\mkdir($directory, self::DIRECTORY_PERMISSIONS, true)',
            $source,
        );
        self::assertStringContainsString('@\\chmod($lockPath, self::FILE_PERMISSIONS)', $source);
    }
}
