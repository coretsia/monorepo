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

namespace Coretsia\Platform\Http\Tests\Support;

use PHPUnit\Framework\Assert;

final class FilesystemPermissionTestSupport
{
    public static function restrict(string $path, int $mode, string $windowsRights): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            self::applyWindowsAcl($path, '/deny', '*S-1-1-0:(' . $windowsRights . ')');
        } else {
            Assert::assertTrue(
                @\chmod($path, $mode),
                'Applying filesystem permission restrictions failed.',
            );
        }

        \clearstatcache(true, $path);
    }

    public static function restore(string $path, int $mode): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            self::applyWindowsAcl($path, '/remove:d', '*S-1-1-0');
        } else {
            Assert::assertTrue(
                @\chmod($path, $mode),
                'Restoring filesystem permissions failed.',
            );
        }

        \clearstatcache(true, $path);
    }

    public static function assertUnreadableFile(string $path): void
    {
        \clearstatcache(true, $path);

        Assert::assertTrue(\is_file($path));

        $handle = @\fopen($path, 'rb');

        if (\is_resource($handle)) {
            \fclose($handle);
        }

        Assert::assertFalse(
            $handle,
            'The fixture must deny file reads before the maintenance behavior is tested.',
        );
    }

    private static function applyWindowsAcl(string $path, string $operation, string $rights): void
    {
        Assert::assertTrue(
            \function_exists('exec'),
            'The Windows permission fixture requires exec().',
        );

        $output = [];
        $exitCode = -1;

        \exec(
            'icacls '
            . \escapeshellarg($path)
            . ' '
            . $operation
            . ' '
            . \escapeshellarg($rights)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        Assert::assertSame(
            0,
            $exitCode,
            'Applying the Windows filesystem ACL operation failed: ' . \implode("\n", $output),
        );
    }
}
