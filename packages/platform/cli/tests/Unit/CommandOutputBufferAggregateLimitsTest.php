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

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Cli\Exception\CliOutputFormatException;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use PHPUnit\Framework\TestCase;

final class CommandOutputBufferAggregateLimitsTest extends TestCase
{
    public function testExactly1024RecordsAreAcceptedAndNextRecordFails(): void
    {
        $buffer = new CommandOutputBuffer();
        for ($index = 0; $index < 1024; ++$index) {
            $buffer->text('ok');
        }
        self::assertRejected(static fn () => $buffer->text('CONFIDENTIAL_1025'));
        self::assertCount(1024, self::storedRecords($buffer));
        self::assertRejected(static fn () => $buffer->finalize());
        $buffer->discard();
        self::assertSame([], $buffer->finalize()->records());

        $exact = new CommandOutputBuffer();
        for ($index = 0; $index < 1024; ++$index) {
            $exact->text('ok');
        }
        self::assertCount(1024, $exact->finalize()->records());
    }

    public function testCanonicalAggregateByteBoundaryAndPlusOne(): void
    {
        $budget = 1048576;
        $fixedSize = self::bytesFor('');
        $maximumTextBytes = $budget - $fixedSize;
        self::assertSame($budget, self::bytesFor(\str_repeat('x', $maximumTextBytes)));

        $accepted = new CommandOutputBuffer();
        $accepted->text(\str_repeat('x', $maximumTextBytes));
        self::assertCount(1, $accepted->finalize()->records());

        $rejected = new CommandOutputBuffer();
        self::assertRejected(static fn () => $rejected->text(\str_repeat('x', $maximumTextBytes + 1)));
        self::assertSame([], self::storedRecords($rejected));
        self::assertRejected(static fn () => $rejected->finalize());
        $rejected->discard();
        self::assertSame([], $rejected->finalize()->records());

        $aggregate = new CommandOutputBuffer();
        $first = 'first';
        $remainder = $budget - self::bytesFor($first) - $fixedSize;
        $aggregate->text($first);
        $aggregate->text(\str_repeat('x', $remainder));
        self::assertCount(2, $aggregate->finalize()->records());

        $overflow = new CommandOutputBuffer();
        $overflow->text($first);
        self::assertRejected(static fn () => $overflow->text(\str_repeat('x', $remainder + 1)));
        self::assertSame([['text' => 'first', 'type' => 'text']], self::storedRecords($overflow));
        self::assertRejected(static fn () => $overflow->finalize());
        $overflow->discard();
        self::assertSame([], $overflow->finalize()->records());
    }

    /** @return list<array<string, mixed>> */
    private static function storedRecords(CommandOutputBuffer $buffer): array
    {
        return new \ReflectionProperty(CommandOutputBuffer::class, 'records')->getValue($buffer);
    }

    private static function bytesFor(string $value): int
    {
        return \strlen(StableJsonEncoder::encodeStable(['text' => $value, 'type' => 'text']));
    }

    private static function assertRejected(\Closure $write): void
    {
        try {
            $write();
            self::fail('Buffer budget overflow accepted.');
        } catch (CliOutputFormatException $exception) {
            self::assertSame(
                CliOutputFormatException::ERROR_CODE . ': ' . CliOutputFormatException::REASON_FORMAT_FAILED,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('CONFIDENTIAL_1025', $exception->getMessage());
            self::assertStringNotContainsString('first', $exception->getMessage());
        }
    }
}
