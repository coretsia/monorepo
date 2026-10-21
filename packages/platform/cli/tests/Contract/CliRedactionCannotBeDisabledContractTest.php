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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Platform\Cli\Exception\RedactionViolationException;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use PHPUnit\Framework\TestCase;

final class CliRedactionCannotBeDisabledContractTest extends TestCase
{
    public function testConfigurationAndGlobalParserExposeNoRedactionSwitch(): void
    {
        $root = \dirname(__DIR__, 2);
        $defaults = require $root . '/config/cli.php';
        $rules = require $root . '/config/rules.php';
        self::assertArrayNotHasKey('redaction', $defaults);
        self::assertArrayNotHasKey('redaction', $rules['keys']);
        self::assertSame(['commands', 'workflows', 'output'], \array_keys($defaults));
        $config = \json_encode([$defaults, $rules], JSON_THROW_ON_ERROR);
        self::assertSame(0, \preg_match('/redaction|redact|bypass|disable_redaction|skip_redaction/i', $config));

        $parser = \file_get_contents($root . '/src/Input/ArgvInputParser.php');
        self::assertIsString($parser);
        self::assertSame(
            0,
            \preg_match(
                '/redact|bypass[-_]?redact/i',
                $parser,
            ),
        );
        $commands = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/src/Command'));
        foreach ($commands as $command) {
            if (!$command->isFile() || $command->getExtension() !== 'php') {
                continue;
            }
            $source = \file_get_contents($command->getPathname());
            self::assertIsString($source);
            self::assertSame(
                0,
                \preg_match(
                    '/redact|bypass[-_]?redact/i',
                    $source,
                ),
            );
            $class = 'Coretsia\\Platform\\Cli\\Command\\' . $command->getBasename('.php');
            self::assertTrue(\class_exists($class));

            foreach ($class::OPTIONS as $option) {
                self::assertIsString($option['name'] ?? null);
                self::assertDoesNotMatchRegularExpression('/redact|bypass/i', $option['name']);
            }
        }
    }

    public function testOutputFormatterRequiresTheSharedRedactionPortAndNeverSkipsItsFailure(): void
    {
        $parameters = new \ReflectionMethod(OutputFormatter::class, '__construct')->getParameters();
        self::assertSame(SensitiveDataRedactorInterface::class, $parameters[0]->getType()?->getName());
        self::assertSame('redactor', $parameters[0]->getName());
        $formatter = new OutputFormatter(
            new class() implements SensitiveDataRedactorInterface {
                public function redactValue(
                    string $value,
                    RedactionKind $kind,
                    RedactionContext $context,
                ): RedactedValue {
                    throw new \LogicException('Redaction failed.');
                }

                public function redactJsonLike(mixed $value, RedactionContext $context): mixed
                {
                    throw new \LogicException('Redaction failed.');
                }
            },
            new JsonFormatter(),
            new TableFormatter(120, new AnsiDecorator()),
            new PlainFormatter(new AnsiDecorator()),
        );
        foreach (['text', 'json', 'error'] as $recordType) {
            $buffer = new CommandOutputBuffer();

            match ($recordType) {
                'text' => $buffer->text('PRIVATE_SECRET'),
                'json' => $buffer->json(['token' => 'PRIVATE_SECRET']),
                'error' => $buffer->error('SAFE_CODE', 'PRIVATE_SECRET'),
            };

            $batch = $buffer->finalize();

            foreach (['json', 'table', 'plain'] as $format) {
                foreach ([false, true] as $color) {
                    try {
                        $formatter->format($batch, 'owner:test', 0, $format, $color);
                        self::fail('Redactor failure must fail closed for all record types, formats and colors.');
                    } catch (RedactionViolationException $exception) {
                        self::assertSame('output-redaction-failed', $exception->reason());
                    }
                }
            }
        }
    }
}
