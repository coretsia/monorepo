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

final class CliJsonPayloadEnvelopeIsNotRedactedTest extends CliIntegrationTestCase
{
    public function testCliOwnedEnvelopePreservesPayloadWhileRedactingOwnerSensitiveKeys(): void
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
            new TableFormatter(100, $ansi),
            new PlainFormatter($ansi),
        );
        $buffer = new CommandOutputBuffer();
        $buffer->json(['z' => 3, 'password' => 'private-password', 'a' => ['safe' => 'visible']]);
        $buffer->text('second-safe-record');
        $buffer->json(['b' => 2, 'a' => 1]);
        $document = \json_decode(
            $formatter->format($buffer->finalize(), 'external:boundary', 0, 'json', false)->stdoutBytes(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('coretsia.cli-output@1', $document['schema']);
        $records = $document['data']['records'];
        self::assertSame(['json', 'text', 'json'], \array_column($records, 'type'));
        self::assertSame(['a', 'password', 'z'], \array_keys($records[0]['payload']));
        self::assertSame(['safe' => 'visible'], $records[0]['payload']['a']);
        self::assertSame(3, $records[0]['payload']['z']);
        self::assertSame(true, $records[0]['payload']['password']['redacted']);
        self::assertSame(null, $records[0]['payload']['password']['hash']);
        self::assertSame(null, $records[0]['payload']['password']['length']);
        self::assertSame('second-safe-record', $records[1]['text']);
        self::assertSame(['a' => 1, 'b' => 2], $records[2]['payload']);
        self::assertStringNotContainsString('private-password', \json_encode($document, JSON_THROW_ON_ERROR));
    }
}
