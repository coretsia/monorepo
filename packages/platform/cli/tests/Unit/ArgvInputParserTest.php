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
}
