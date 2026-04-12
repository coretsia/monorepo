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

use Coretsia\Tools\Spikes\_support\ConsoleOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsoleOutputSanitizePolicyTest extends TestCase
{
    /** @var resource|null */
    private $memOut = null;

    /** @var resource|null */
    private $memErr = null;

    /** @var mixed */
    private $prevOut = null;

    /** @var mixed */
    private $prevErr = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memOut = fopen('php://memory', 'wb+');
        $this->memErr = fopen('php://memory', 'wb+');

        self::assertIsResource($this->memOut);
        self::assertIsResource($this->memErr);

        [$this->prevOut, $this->prevErr] = self::swapConsoleStreams($this->memOut, $this->memErr);
    }

    protected function tearDown(): void
    {
        // Restore previous streams.
        self::restoreConsoleStreams($this->prevOut, $this->prevErr);

        if (is_resource($this->memOut)) {
            fclose($this->memOut);
        }
        if (is_resource($this->memErr)) {
            fclose($this->memErr);
        }

        parent::tearDown();
    }

    public function test_safe_line_is_trimmed_and_emitted_with_single_lf_newline(): void
    {
        ConsoleOutput::line("  ok  ", false);

        $stdout = $this->readAll($this->memOut);

        self::assertSame("ok\n", $stdout);
        self::assertSame('', $this->readAll($this->memErr));
    }

    public function test_multiline_is_truncated_to_first_line_only(): void
    {
        ConsoleOutput::line("ok\nleak=1", false);

        $stdout = $this->readAll($this->memOut);

        // First line is safe => kept; second line must not be emitted.
        self::assertSame("ok\n", $stdout);
    }

    #[DataProvider('unsafeLines')]
    public function test_unsafe_line_is_replaced_with_unsafe_output_token(string $input): void
    {
        ConsoleOutput::line($input, false);

        $stdout = $this->readAll($this->memOut);

        self::assertSame("unsafe-output\n", $stdout);
    }

    public function test_lines_non_string_element_is_replaced_with_unsafe_output_token(): void
    {
        /** @var array $lines */
        $lines = ['ok', 123, 'also-ok'];

        ConsoleOutput::lines($lines, false);

        $stdout = $this->readAll($this->memOut);

        self::assertSame("ok\nunsafe-output\nalso-ok\n", $stdout);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function unsafeLines(): array
    {
        return [
            'empty' => [''],
            'contains-null-byte' => ["x\0y"],
            'contains-ansi-escape' => ["\x1B[31mred\x1B[0m"],
            'dotenv-like-equals' => ['TOKEN=secret'],
            'any-equals' => ['a=b'],
            'contains-backslash' => ['C:\\x'],
            'contains-url-scheme' => ['http://example.com'],
            'posix-absolute-leading-slash' => ['/etc/passwd'],
            'posix-home-hint' => ['see /home/user/.env'],
            'macos-home-hint' => ['see /Users/user/.env'],
            'windows-drive-hint' => ['C:/x'],
            'windows-unc-hint' => ['\\\\server\\share\\file'],
            'only-spaces' => ['   '],
        ];
    }

    /**
     * @param resource $h
     */
    private function readAll($h): string
    {
        self::assertIsResource($h);

        rewind($h);
        $s = stream_get_contents($h);
        if ($s === false) {
            return '';
        }
        return (string)$s;
    }

    /**
     * @param resource $out
     * @param resource $err
     * @return array{0:mixed,1:mixed} previous values
     */
    private static function swapConsoleStreams($out, $err): array
    {
        $ref = new \ReflectionClass(ConsoleOutput::class);

        $pOut = $ref->getProperty('stdout');
        $pErr = $ref->getProperty('stderr');

        $prevOut = $pOut->getValue();
        $prevErr = $pErr->getValue();

        $pOut->setValue(null, $out);
        $pErr->setValue(null, $err);

        return [$prevOut, $prevErr];
    }

    private static function restoreConsoleStreams($prevOut, $prevErr): void
    {
        $ref = new \ReflectionClass(ConsoleOutput::class);

        $pOut = $ref->getProperty('stdout');
        $pErr = $ref->getProperty('stderr');

        $pOut->setValue(null, $prevOut);
        $pErr->setValue(null, $prevErr);
    }
}
