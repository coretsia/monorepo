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

namespace Coretsia\Kernel\Tests\Support;

final class FilesystemLinkTestSupport
{
    public static function symlink(
        string $target,
        string $link,
        bool $directory = false,
    ): void {
        if (
            \function_exists('symlink')
            && @\symlink($target, $link)
            && \is_link($link)
        ) {
            return;
        }

        if (\PHP_OS_FAMILY === 'Windows' && \function_exists('exec')) {
            $output = [];
            $exitCode = -1;

            \exec(
                'mklink '
                . ($directory ? '/D ' : '')
                . \escapeshellarg($link)
                . ' '
                . \escapeshellarg($target)
                . ' 2>&1',
                $output,
                $exitCode,
            );

            if ($exitCode === 0 && \is_link($link)) {
                return;
            }
        }

        throw new \RuntimeException(
            'Creating a real filesystem symbolic link failed. On Windows, enable Developer Mode '
            . 'or grant the test process the Create symbolic links privilege.',
        );
    }
}
