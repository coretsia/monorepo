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

use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use PHPUnit\Framework\TestCase;

final class OutputFormatterUsesSensitiveDataRedactorTest extends TestCase
{
    public function testAllOwnerValuesUseSharedRedactionAndEnvelopeIsPreserved(): void
    {
        $port = new OutputFormatterRedactionSpy();
        $formatter = new OutputFormatter(
            $port,
            new JsonFormatter(),
            new TableFormatter(80, new AnsiDecorator()),
            new PlainFormatter(new AnsiDecorator()),
        );
        $buffer = new CommandOutputBuffer();
        $buffer->text('SECRET_TEXT');
        $buffer->json(['z' => 'SECRET_JSON', 'a' => 'public']);
        $buffer->error('SAFE_ERROR', 'SECRET_ERROR');
        $batch = $buffer->finalize();
        $result = $formatter->format($batch, 'owner:run', 1, 'json', true);
        self::assertSame('', $result->stderrBytes());
        self::assertStringNotContainsString('SECRET_TEXT', $result->stdoutBytes());
        self::assertStringNotContainsString('SECRET_JSON', $result->stdoutBytes());
        self::assertStringNotContainsString('SECRET_ERROR', $result->stdoutBytes());
        self::assertStringNotContainsString("\x1B", $result->stdoutBytes());
        $decoded = \json_decode($result->stdoutBytes(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['text' => '[REDACTED]', 'type' => 'text'], $decoded['data']['records'][0]);
        self::assertSame(['a', 'z'], \array_keys($decoded['data']['records'][1]['payload']));
        self::assertSame('public', $decoded['data']['records'][1]['payload']['a']);
        self::assertSame('[REDACTED]', $decoded['data']['records'][1]['payload']['z']);
        self::assertSame(
            ['code' => 'SAFE_ERROR', 'message' => '[REDACTED]', 'type' => 'error'],
            $decoded['data']['records'][2]
        );
        self::assertCount(3, $port->received);
        self::assertSame('SECRET_TEXT', $port->received[0]['value']);
        self::assertSame(['a' => 'public', 'z' => 'SECRET_JSON'], $port->received[1]['value']);
        self::assertSame('SECRET_ERROR', $port->received[2]['value']);
        foreach ($port->received as $call) {
            self::assertSame('cli.output', $call['context']->scope());
            self::assertSame(RedactionMode::Placeholder, $call['context']->mode());
        }
    }

    public function testTextAndErrorRemainStringsAfterUnexpectedPortShapes(): void
    {
        $port = new OutputFormatterRedactionSpy(returnNonString: true);
        $formatter = new OutputFormatter(
            $port,
            new JsonFormatter(),
            new TableFormatter(80, new AnsiDecorator()),
            new PlainFormatter(new AnsiDecorator()),
        );
        $buffer = new CommandOutputBuffer();
        $buffer->text('SECRET_TEXT');
        $buffer->error('ERR', 'SECRET_ERROR');
        $result = $formatter->format($buffer->finalize(), null, 1, 'json', false);
        $records = \json_decode($result->stdoutBytes(), true, flags: JSON_THROW_ON_ERROR)['data']['records'];
        self::assertSame('[REDACTED]', $records[0]['text']);
        self::assertSame('[REDACTED]', $records[1]['message']);
    }
}

final class OutputFormatterRedactionSpy implements SensitiveDataRedactorInterface
{
    /** @var list<array{value:mixed,context:RedactionContext}> */
    public array $received = [];

    public function __construct(private bool $returnNonString = false)
    {
    }

    public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
    {
        throw new \LogicException('Only the recursive shared-redaction port is permitted.');
    }

    public function redactJsonLike(mixed $value, RedactionContext $context): mixed
    {
        $this->received[] = ['value' => $value, 'context' => $context];
        if (\is_string($value)) {
            if ($this->returnNonString) {
                return ['not' => 'a string'];
            }
            return \str_starts_with($value, 'SECRET_') ? '[REDACTED]' : $value;
        }
        if (!\is_array($value)) {
            return $value;
        }
        $result = [];
        foreach ($value as $key => $entry) {
            $result[$key] = $this->redactFixtureEntry($entry);
        }
        return $result;
    }

    private function redactFixtureEntry(mixed $value): mixed
    {
        if (\is_string($value)) {
            return \str_starts_with($value, 'SECRET_') ? '[REDACTED]' : $value;
        }
        if (!\is_array($value)) {
            return $value;
        }
        $result = [];
        foreach ($value as $key => $entry) {
            $result[$key] = $this->redactFixtureEntry($entry);
        }
        return $result;
    }
}
