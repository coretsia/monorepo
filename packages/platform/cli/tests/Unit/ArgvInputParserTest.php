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

use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArgvInputParserTest extends TestCase
{
    #[DataProvider('repeatedInvalidOptions')]
    public function testRepeatedFlagsAndMixedValuedOccurrencesFail(array $argv): void
    {
        try {
            new ArgvInputParser()->parse($argv);
        } catch (CliInputInvalidException $exception) {
            self::assertSame(CliInputInvalidException::REASON_OPTION_REPEATED, $exception->reason());
            self::assertSame(CliInputInvalidException::ERROR_CODE, $exception->errorCode());
            return;
        }
        self::fail('Repeated or mixed bare/valued option must fail deterministically.');
    }

    /** @return iterable<string, array{list<string>}> */
    public static function repeatedInvalidOptions(): iterable
    {
        yield 'same-bare-flag' => [['coretsia', 'external:mode', '--verbose', '--verbose']];
        yield 'bare-then-valued' => [['coretsia', 'external:mode', '--item', '--item=one']];
        yield 'valued-then-bare' => [['coretsia', 'external:mode', '--item=one', '--item']];
        yield 'bare-after-list' => [['coretsia', 'external:mode', '--item=one', '--item=two', '--item']];
        yield 'duplicate-global-format' => [['coretsia', 'list', '--format=json', '--format=table']];
        yield 'duplicate-global-color' => [['coretsia', 'list', '--color=never', '--color=always']];
    }

    public function testRepeatedValuedOptionsRetainOriginalOrder(): void
    {
        $parsed = new ArgvInputParser()->parse([
            'coretsia',
            'external:mode',
            '--item=third',
            '--item=first',
            '--mode=fast',
            '--item=second',
        ]);
        self::assertSame(['third', 'first', 'second'], $parsed->commandInput->option('item'));
        self::assertSame('fast', $parsed->commandInput->option('mode'));
        self::assertSame(
            ['external:mode', '--item=third', '--item=first', '--mode=fast', '--item=second'],
            $parsed->commandInput->tokens(),
        );
        self::assertNull($parsed->formatOverride);
        self::assertNull($parsed->colorOverride);
    }

    public function testEndOfOptionsMarkerMakesGlobalLookingTokensPositional(): void
    {
        $parsed = new ArgvInputParser()->parse([
            'coretsia',
            'external:mode',
            '--item=one',
            '--',
            '--format=json',
            '--color=always',
            '--item=two',
        ]);
        self::assertSame(['--format=json', '--color=always', '--item=two'], $parsed->commandInput->arguments());
        self::assertSame(['item' => 'one'], $parsed->commandInput->options());
        self::assertSame(
            ['external:mode', '--item=one', '--', '--format=json', '--color=always', '--item=two'],
            $parsed->commandInput->tokens(),
        );
        self::assertNull($parsed->formatOverride);
        self::assertNull($parsed->colorOverride);
        self::assertFalse($parsed->commandInput->hasOption('format'));
        self::assertFalse($parsed->commandInput->hasOption('color'));
    }

    public function testGlobalOptionsBeforeMarkerRemainOnlyInInvocationPresentationFields(): void
    {
        $parsed = new ArgvInputParser()->parse([
            'coretsia',
            'external:mode',
            '--format=table',
            '--color=always',
            '--item=one',
            '--',
            '--format=json',
        ]);
        self::assertSame('table', $parsed->formatOverride);
        self::assertSame('always', $parsed->colorOverride);
        self::assertSame(['--format=json'], $parsed->commandInput->arguments());
        self::assertSame(['item' => 'one'], $parsed->commandInput->options());
        self::assertSame(['external:mode', '--item=one', '--', '--format=json'], $parsed->commandInput->tokens());
    }

    public function testThousandsOfDistinctCommandOptionsPreserveExactNamesAndValues(): void
    {
        $tokens = ['coretsia', 'external:mode'];
        $expected = [];
        for ($index = 0; $index < 4096; ++$index) {
            $name = 'option-' . $index;
            $value = 'value-' . $index;
            $tokens[] = '--' . $name . '=' . $value;
            $expected[$name] = $value;
        }

        $parsed = new ArgvInputParser()->parse($tokens);
        self::assertSame($expected, $parsed->commandInput->options());
        self::assertSame(4096, \count($parsed->commandInput->options()));
        self::assertSame(\array_slice($tokens, 1), $parsed->commandInput->tokens());
        self::assertSame([], $parsed->commandInput->arguments());
    }

    public function testTwentyThousandRepeatedValuedOptionsPreserveCountAndOrder(): void
    {
        $tokens = ['coretsia', 'external:mode'];
        $values = [];
        for ($index = 0; $index < 20000; ++$index) {
            $value = 'item-' . $index;
            $values[] = $value;
            $tokens[] = '--item=' . $value;
        }

        $parser = new ArgvInputParser();
        $first = $parser->parse($tokens);
        self::assertSame(20000, \count($first->commandInput->option('item')));
        self::assertSame($values, $first->commandInput->option('item'));
        self::assertSame(\array_slice($tokens, 1), $first->commandInput->tokens());

        $second = $parser->parse($tokens);
        self::assertSame($first->commandInput->options(), $second->commandInput->options());
        self::assertSame($first->commandInput->tokens(), $second->commandInput->tokens());
    }

    public function testLargeAndNonUtf8OptionAndArgumentValuesRemainUnmodified(): void
    {
        $largeValue = \str_repeat("payload\x00\xFF", 32768);
        $invalidUtf8 = "\xC3\x28\xFE";
        $parsed = new ArgvInputParser()->parse([
            'coretsia',
            'external:mode',
            '--blob=' . $largeValue,
            '--raw=' . $invalidUtf8,
            '--',
            $largeValue,
            $invalidUtf8,
        ]);
        self::assertSame($largeValue, $parsed->commandInput->option('blob'));
        self::assertSame($invalidUtf8, $parsed->commandInput->option('raw'));
        self::assertSame([$largeValue, $invalidUtf8], $parsed->commandInput->arguments());
        self::assertSame(
            ['external:mode', '--blob=' . $largeValue, '--raw=' . $invalidUtf8, '--', $largeValue, $invalidUtf8],
            $parsed->commandInput->tokens(),
        );
    }

    public function testInvalidUtf8NamesAndMalformedSequencesUseFixedSafeReasons(): void
    {
        $cases = [
            [['coretsia', "external:\xFF"], CliInputInvalidException::REASON_COMMAND_INVALID],
            [['coretsia', "\xFF"], CliInputInvalidException::REASON_COMMAND_INVALID],
            [['coretsia', 'external:mode', "--bad\xFF=value"], CliInputInvalidException::REASON_OPTION_INVALID],
            [['coretsia', 'external:mode', '--=PRIVATE_VALUE'], CliInputInvalidException::REASON_OPTION_INVALID],
            [['coretsia', '--', 'PRIVATE_VALUE'], CliInputInvalidException::REASON_COMMAND_MISSING],
            [
                ['coretsia', 'external:mode', '--flag', '--flag=PRIVATE_VALUE'],
                CliInputInvalidException::REASON_OPTION_REPEATED,
            ],
            [
                ['coretsia', 'external:mode', '--format=json', '--FORMAT=table'],
                CliInputInvalidException::REASON_OPTION_REPEATED,
            ],
            [
                ['coretsia', 'external:mode', '--color=never', '--color=always'],
                CliInputInvalidException::REASON_OPTION_REPEATED,
            ],
            [
                ['coretsia', 'external:mode', '--format', '--format=json'],
                CliInputInvalidException::REASON_OPTION_INVALID,
            ],
        ];

        $parser = new ArgvInputParser();
        foreach ($cases as [$tokens, $reason]) {
            for ($attempt = 0; $attempt < 2; ++$attempt) {
                try {
                    $parser->parse($tokens);
                    self::fail('Malformed argv must fail with a deterministic exception.');
                } catch (CliInputInvalidException $exception) {
                    self::assertSame(CliInputInvalidException::ERROR_CODE, $exception->errorCode());
                    self::assertSame($reason, $exception->reason());
                    self::assertSame(CliInputInvalidException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
                    foreach (['PRIVATE_VALUE', "\xFF", "\xFE"] as $secret) {
                        self::assertStringNotContainsString($secret, $exception->getMessage());
                    }
                }
            }
        }
    }
}
