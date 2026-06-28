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

namespace Coretsia\Tools\Tests\Unit;

use Coretsia\Tools\Spikes\_support\DeterministicFile;
use PHPUnit\Framework\TestCase;

final class DeterministicFileAtomicWriteTest extends TestCase
{
    public function testWriteTextLfUsesAtomicTempAndCleansItUp(): void
    {
        $dir = $this->makeTempDir('coretsia_atomic_file_');
        $target = $dir . '/example.txt';
        $tmp = $dir . '/.example.txt.coretsia-tmp';

        self::assertNotFalse(file_put_contents($target, "old\n"));
        self::assertNotFalse(file_put_contents($tmp, "stale-temp\n"));

        DeterministicFile::writeTextLf($target, "new\r\nvalue");

        self::assertSame("new\nvalue\n", file_get_contents($target));
        self::assertFileDoesNotExist($tmp);
    }

    public function testWriteBytesExactPreservesEmptyPayloadExactly(): void
    {
        $dir = $this->makeTempDir('coretsia_atomic_file_empty_');
        $target = $dir . '/empty.bin';
        $tmp = $dir . '/.empty.bin.coretsia-tmp';

        DeterministicFile::writeBytesExact($target, '');

        self::assertSame('', file_get_contents($target));
        self::assertFileDoesNotExist($tmp);
    }

    private function makeTempDir(string $prefix): string
    {
        $dir = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/')
            . '/'
            . $prefix
            . bin2hex(random_bytes(6));

        self::assertTrue(mkdir($dir, 0777, true));

        return $dir;
    }
}
