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

use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;

final class CliSensitiveTextRetainsStringRecordShapeTest extends CliIntegrationTestCase
{
    public function testSensitiveTextReturnsOpaqueStringInsteadOfStructuredSummary(): void
    {
        $redactor = new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
        $ansi = new AnsiDecorator();
        $formatter = new OutputFormatter(
            $redactor,
            new JsonFormatter(),
            new TableFormatter(80, $ansi),
            new PlainFormatter($ansi),
        );
        $secret = 'Bearer abcdefghijklmnopqrstuvwxyz1234567890';
        $buffer = new CommandOutputBuffer();
        $buffer->text($secret);
        $buffer->error('SAFE_ERROR', $secret);
        $json = $formatter->format($buffer->finalize(), 'external:boundary', 1, 'json', true)->stdoutBytes();
        $records = \json_decode($json, true, flags: JSON_THROW_ON_ERROR)['data']['records'];
        self::assertSame(['text' => '[REDACTED]', 'type' => 'text'], $records[0]);
        self::assertSame(['code' => 'SAFE_ERROR', 'message' => '[REDACTED]', 'type' => 'error'], $records[1]);
        self::assertStringNotContainsString($secret, $json);
        self::assertStringNotContainsString('schemaVersion', $json);
        self::assertStringNotContainsString('hash', $json);
        self::assertStringNotContainsString('length', $json);
    }
}
