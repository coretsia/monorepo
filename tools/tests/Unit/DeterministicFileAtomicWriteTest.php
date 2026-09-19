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

use Coretsia\Tools\Support\DeterministicFile;
use PHPUnit\Framework\TestCase;

final class DeterministicFileAtomicWriteTest extends TestCase
{
    public function testWriteTextLfUsesAtomicTempAndCleansItUp(): void
    {
        $dir = $this->makeTempDir('coretsia_atomic_file_');

        try {
            $target = $dir . '/example.txt';

            self::assertNotFalse(file_put_contents($target, "old\n"));

            DeterministicFile::writeTextLf($target, "new\r\nvalue");

            self::assertSame("new\nvalue\n", file_get_contents($target));
            $this->assertNoAtomicTempFiles($dir, 'example.txt');
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testWriteBytesExactPreservesEmptyPayloadExactly(): void
    {
        $dir = $this->makeTempDir('coretsia_atomic_file_empty_');

        try {
            $target = $dir . '/empty.bin';

            DeterministicFile::writeBytesExact($target, '');

            self::assertSame('', file_get_contents($target));
            $this->assertNoAtomicTempFiles($dir, 'empty.bin');
        } finally {
            $this->removeTempDir($dir);
        }
    }

    private function assertNoAtomicTempFiles(string $dir, string $targetBase): void
    {
        $tmpFiles = glob($dir . '/.' . $targetBase . '.coretsia-tmp-*');

        self::assertIsArray($tmpFiles);
        self::assertSame([], $tmpFiles);
    }

    private function removeTempDir(string $dir): void
    {
        $entries = scandir($dir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            @unlink($dir . '/' . $entry);
        }

        @rmdir($dir);
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
