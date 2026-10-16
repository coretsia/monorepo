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

final class CommandOutputBufferRejectsControlCharactersInMapKeysTest extends TestCase
{
    #[DataProvider('forbiddenKeys')]
    public function testRejectsMapKeysWithoutNormalizingOrLeakingThem(string $key): void
    {
        $buffer = new CommandOutputBuffer();
        try {
            $buffer->json(['outer' => [$key => 'SECRET_VALUE']]);
            self::fail('Forbidden map key accepted.');
        } catch (CliOutputFormatException $exception) {
            self::assertSame(CliOutputFormatException::REASON_FORMAT_FAILED, $exception->reason());
            self::assertStringNotContainsString('SECRET_VALUE', $exception->getMessage());
            self::assertStringNotContainsString($key, $exception->getMessage());
            $buffer->discard();
            self::assertSame([], $buffer->finalize()->records());
        }
    }

    public static function forbiddenKeys(): iterable
    {
        yield 'crlf' => ["key\r\nname"];
        yield 'cr' => ["key\rname"];
        yield 'lf' => ["key\nname"];
        yield 'tab' => ["key\tname"];
        yield 'nul' => ["key\0name"];
        yield 'esc' => ["key\x1Bname"];
        yield 'del' => ["key\x7Fname"];
        yield 'c1' => ["key\u{0085}name"];
        yield 'malformed' => ["key\xC3\x28"];
    }

    public function testUnicodeKeysKeepOriginalBytesAndFoundationOrdering(): void
    {
        $buffer = new CommandOutputBuffer();
        $buffer->json(['值' => "line\r\nnext\tcell", 'Київ' => 'місто', 'a' => 'ASCII']);
        $records = $buffer->finalize()->records();
        self::assertSame(['a', 'Київ', '值'], \array_keys($records[0]['payload']));
        self::assertSame("line\nnext\tcell", $records[0]['payload']['值']);
        self::assertSame('місто', $records[0]['payload']['Київ']);
    }
}
