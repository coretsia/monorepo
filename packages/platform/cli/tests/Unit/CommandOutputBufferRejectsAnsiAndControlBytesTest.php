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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Platform\Cli\Exception\CliOutputFormatException;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandOutputBufferRejectsAnsiAndControlBytesTest extends TestCase
{
    #[DataProvider('forbiddenStrings')]
    public function testRejectsForbiddenControlsWithoutDisclosingValues(string $value): void
    {
        foreach (['text', 'json', 'error'] as $kind) {
            $buffer = new CommandOutputBuffer();
            try {
                match ($kind) {
                    'text' => $buffer->text($value),
                    'json' => $buffer->json(['field' => $value]),
                    'error' => $buffer->error('SAFE', $value),
                };
                self::fail('Unsafe output accepted: ' . $kind);
            } catch (CliOutputFormatException $exception) {
                self::assertSame(
                    CliOutputFormatException::ERROR_CODE . ': ' . CliOutputFormatException::REASON_FORMAT_FAILED,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString($value, $exception->getMessage());
                $buffer->discard();
                self::assertSame([], $buffer->finalize()->records());
            }
        }
    }

    public static function forbiddenStrings(): iterable
    {
        yield 'nul' => ["ab\0cd"];
        yield 'escape' => ["ab\x1Bcd"];
        yield 'ansi' => ["\x1B[31mSECRET\x1B[0m"];
        yield 'backspace' => ["ab\x08cd"];
        yield 'vertical-tab' => ["ab\x0Bcd"];
        yield 'del' => ["ab\x7Fcd"];
        yield 'unicode-c1' => ["ab\u{0085}cd"];
    }

    public function testPermittedLineEndingsAndUtf8ArePreservedSafely(): void
    {
        $buffer = new CommandOutputBuffer();
        $buffer->text("Zażółć 😀\r\nline\rnext\tcell");
        $buffer->json(['text' => "Київ\r\nрядок\t值"]);
        $buffer->error('SAFE', "Помилка\tопис");
        self::assertSame([
            ['text' => "Zażółć 😀\nline\nnext\tcell", 'type' => 'text'],
            ['payload' => ['text' => "Київ\nрядок\t值"], 'type' => 'json'],
            ['code' => 'SAFE', 'message' => "Помилка\tопис", 'type' => 'error'],
        ], $buffer->finalize()->records());
    }

    public function testMultilineErrorIsRejectedAndMalformedUtf8FailsClosed(): void
    {
        foreach (["line\r\nother", "line\rother", "line\nother", "bad\xC3\x28"] as $message) {
            $buffer = new CommandOutputBuffer();
            try {
                $buffer->error('SAFE', $message);
                self::fail('Invalid error message accepted.');
            } catch (CliOutputFormatException $exception) {
                self::assertStringNotContainsString($message, $exception->getMessage());
                $buffer->discard();
                self::assertSame([], $buffer->finalize()->records());
            }
        }
        foreach (['text', 'json'] as $kind) {
            $buffer = new CommandOutputBuffer();
            try {
                if ($kind === 'text') {
                    $buffer->text("bad\xC3\x28");
                } else {
                    $buffer->json(['value' => "bad\xC3\x28"]);
                }
                self::fail('Malformed UTF-8 accepted.');
            } catch (CliOutputFormatException $exception) {
                self::assertSame(CliOutputFormatException::REASON_FORMAT_FAILED, $exception->reason());
                $buffer->discard();
                self::assertSame([], $buffer->finalize()->records());
            }
        }
    }
}
