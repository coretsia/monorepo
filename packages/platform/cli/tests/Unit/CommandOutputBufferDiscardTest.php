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
use PHPUnit\Framework\TestCase;

final class CommandOutputBufferDiscardTest extends TestCase
{
    public function testDiscardRemovesAllPreviousRecordsAndRemainsWritable(): void
    {
        $buffer = new CommandOutputBuffer();
        $buffer->text('discarded text');
        $buffer->json(['secret' => 'discarded json']);
        $buffer->error('SAFE', 'discarded error');
        $buffer->discard();
        $buffer->json(['status' => 'ok']);
        self::assertSame([['payload' => ['status' => 'ok'], 'type' => 'json']], $buffer->finalize()->records());
    }

    public function testDiscardCannotBeCalledAfterFinalization(): void
    {
        $buffer = new CommandOutputBuffer();
        $buffer->text('ok');
        $buffer->finalize();
        $this->expectException(CliOutputFormatException::class);
        $buffer->discard();
    }

    public function testDiscardResetsAggregateBudgetAndFailureState(): void
    {
        $buffer = new CommandOutputBuffer();
        try {
            $buffer->text(\str_repeat('a', 1048576));
            self::fail('Oversize text accepted.');
        } catch (CliOutputFormatException) {
            $buffer->discard();
        }
        $buffer->text('recovered');
        self::assertSame([['text' => 'recovered', 'type' => 'text']], $buffer->finalize()->records());
    }
}
