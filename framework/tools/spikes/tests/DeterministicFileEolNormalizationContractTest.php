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

namespace Coretsia\Tools\Spikes\tests;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use PHPUnit\Framework\TestCase;

final class DeterministicFileEolNormalizationContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'coretsia-spikes-deterministicfile-'
            . substr(hash('sha256', __FILE__), 0, 16);

        $this->rmDir($this->root);
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmDir($this->root);
    }

    public function testReadTextNormalizedEolNormalizesCrLfAndCrToLf(): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'read-normalize.txt';

        file_put_contents($path, "a\r\nb\rc\n");

        $actual = DeterministicFile::readTextNormalizedEol($path);

        self::assertSame("a\nb\nc\n", $actual);
    }

    public function testHashSha256NormalizedEolIsStableAcrossCrLfAndLfEquivalentContent(): void
    {
        $lf = $this->root . DIRECTORY_SEPARATOR . 'a-lf.txt';
        $crlf = $this->root . DIRECTORY_SEPARATOR . 'b-crlf.txt';

        file_put_contents($lf, "one\ntwo\nthree\n");
        file_put_contents($crlf, "one\r\ntwo\r\nthree\r\n");

        $h1 = DeterministicFile::hashSha256NormalizedEol($lf);
        $h2 = DeterministicFile::hashSha256NormalizedEol($crlf);

        self::assertSame($h1, $h2);
    }

    public function testWriteTextLfGuaranteesLfOnlyAndFinalNewline(): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'write-text-lf.txt';

        DeterministicFile::writeTextLf($path, "a\r\nb\rc");

        $raw = file_get_contents($path);

        self::assertIsString($raw);
        self::assertSame("a\nb\nc\n", $raw);
        self::assertStringNotContainsString("\r", $raw);
        self::assertTrue(str_ends_with($raw, "\n"));
    }

    public function testReadMissingFileThrowsDeterministicReadFailedWithoutLeakingPathInMessage(): void
    {
        $token = 'MISSING_TOKEN_12345';
        $path = $this->root . DIRECTORY_SEPARATOR . $token . '.txt';

        try {
            DeterministicFile::readTextNormalizedEol($path);
            self::fail('Expected DeterministicException was not thrown.');
        } catch (DeterministicException $e) {
            $this->assertDeterministicCode($e, ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED);
            self::assertSame('spikes-io-read-failed', $e->getMessage());
            self::assertNull($e->getPrevious());

            self::assertStringNotContainsString($token, $e->getMessage());
            self::assertStringNotContainsString($path, $e->getMessage());
        }
    }

    public function testReadValueErrorNullByteIsMappedToReadFailedAndDoesNotLeakPathInMessage(): void
    {
        $token = 'NULLBYTE_READ_TOKEN_ABCDE';
        $path = $this->root . DIRECTORY_SEPARATOR . $token . "\0" . 'x.txt';

        // Precondition: native filesystem API must throw ValueError for null byte paths.
        $this->assertNativeValueError(static fn (): mixed => file_get_contents($path));

        try {
            DeterministicFile::readTextNormalizedEol($path);
            self::fail('Expected DeterministicException was not thrown.');
        } catch (DeterministicException $e) {
            $this->assertDeterministicCode($e, ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED);
            self::assertSame('spikes-io-read-failed', $e->getMessage());
            self::assertNull($e->getPrevious());

            // No path/token leak.
            self::assertStringNotContainsString($token, $e->getMessage());
            self::assertStringNotContainsString('x.txt', $e->getMessage());
            self::assertStringNotContainsString($this->root, $e->getMessage());
        }
    }

    public function testWriteBytesExactWritesBytesUnchanged(): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'bytes-exact.bin';
        $bytes = "x\r\ny\rz\n" . "\x00" . "\xFF";

        DeterministicFile::writeBytesExact($path, $bytes);

        $raw = file_get_contents($path);

        self::assertIsString($raw);
        self::assertSame($bytes, $raw);
    }

    public function testWriteBytesExactFailureThrowsDeterministicWriteFailedWithoutLeakingPathInMessage(): void
    {
        $token = 'TARGET_TOKEN_67890';
        $dirAsFile = $this->root . DIRECTORY_SEPARATOR . $token;

        mkdir($dirAsFile, 0777, true);

        try {
            DeterministicFile::writeBytesExact($dirAsFile, 'abc');
            self::fail('Expected DeterministicException was not thrown.');
        } catch (DeterministicException $e) {
            $this->assertDeterministicCode($e, ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED);
            self::assertSame('spikes-io-write-failed', $e->getMessage());
            self::assertNull($e->getPrevious());

            self::assertStringNotContainsString($token, $e->getMessage());
            self::assertStringNotContainsString($dirAsFile, $e->getMessage());
        }
    }

    public function testWriteValueErrorNullByteIsMappedToWriteFailedAndDoesNotLeakPathInMessage(): void
    {
        $token = 'NULLBYTE_WRITE_TOKEN_QWERTY';
        $path = $this->root . DIRECTORY_SEPARATOR . $token . "\0" . 'y.bin';

        // Precondition: native filesystem API must throw ValueError for null byte paths.
        $this->assertNativeValueError(static fn (): mixed => fopen($path, 'wb'));

        try {
            DeterministicFile::writeBytesExact($path, 'abc');
            self::fail('Expected DeterministicException was not thrown.');
        } catch (DeterministicException $e) {
            $this->assertDeterministicCode($e, ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED);
            self::assertSame('spikes-io-write-failed', $e->getMessage());
            self::assertNull($e->getPrevious());

            // No path/token leak.
            self::assertStringNotContainsString($token, $e->getMessage());
            self::assertStringNotContainsString('y.bin', $e->getMessage());
            self::assertStringNotContainsString($this->root, $e->getMessage());
        }
    }

    private function assertNativeValueError(callable $operation): void
    {
        try {
            $operation();
            self::fail('Precondition failed: expected native ValueError was not thrown.');
        } catch (\ValueError) {
            self::assertTrue(true);
        } catch (\Throwable $t) {
            self::fail('Precondition failed: expected ValueError, got ' . $t::class . '.');
        }
    }

    private function assertDeterministicCode(DeterministicException $e, string $expected): void
    {
        $candidates = [
            'deterministicCode',
            'getDeterministicCode',
            'code',
            'errorCode',
            'getErrorCode',
        ];

        foreach ($candidates as $method) {
            if (method_exists($e, $method)) {
                $actual = $e->{$method}();
                self::assertSame($expected, $actual);

                return;
            }
        }

        self::fail('DeterministicException does not expose a deterministic code accessor.');
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->rmDir($path);
                @rmdir($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
