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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class JsonFormatterAndConsoleWriterOwnOneFinalNewlineTest extends CliIntegrationTestCase
{
    public function testEncoderFormatterAndWriterContributeExactlyOneTerminalLf(): void
    {
        self::assertTrue(\str_ends_with(StableJsonEncoder::encodeStableMap(['safe' => true]), "\n"));
        $buffer = new CommandOutputBuffer();
        $buffer->json(['safe' => true]);
        $formatted = new JsonFormatter()->format($buffer->finalize(), 'external:boundary', 0);
        self::assertFalse(\str_ends_with($formatted->stdoutBytes(), "\n"));
        self::assertSame('', $formatted->stderrBytes());
        $out = \fopen('php://temp', 'w+');
        $err = \fopen('php://temp', 'w+');
        self::assertIsResource($out);
        self::assertIsResource($err);
        try {
            $writer = new ConsoleOutputWriter($out, $err);
            $writer->write($formatted);
            \rewind($out);
            \rewind($err);
            $stdout = (string) \stream_get_contents($out);
            self::assertSame($formatted->stdoutBytes() . "\n", $stdout);
            self::assertTrue(\str_ends_with($stdout, "\n"));
            self::assertFalse(\str_ends_with($stdout, "\n\n"));
            self::assertStringNotContainsString("\x1B", $stdout);
            self::assertSame('', \stream_get_contents($err));
            self::assertSame(
                'coretsia.cli-output@1',
                \json_decode($stdout, true, flags: JSON_THROW_ON_ERROR)['schema'],
            );
        } finally {
            \fclose($out);
            \fclose($err);
        }
    }
}
