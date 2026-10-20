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

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\UX\SmartSuggestor;
use PHPUnit\Framework\TestCase;

final class SmartSuggestorInputBoundsTest extends TestCase
{
    public function testInvalidOrOversizedUnknownTokensReturnNoSuggestionsEvenWhenClose(): void
    {
        $catalog = self::catalog([
            InputBoundsNearCommand::class,
            InputBoundsLength128Command::class,
        ]);
        $suggestor = new SmartSuggestor();
        self::assertSame(['testcmc'], $suggestor->suggest('testcmx', $catalog));
        foreach (
            [
                'testcmX',
                'testcm:',
                'testcm ',
                "testcm\n",
                "testcm\xC3\xA9",
                '',
                \str_repeat('a', 129),
            ] as $invalid
        ) {
            self::assertSame([], $suggestor->suggest($invalid, $catalog));
        }
        self::assertSame([], $suggestor->suggest(\str_repeat('a', 1024), $catalog));
        self::assertSame([], $suggestor->suggest(\str_repeat('a', 128) . '\\n', $catalog));
    }

    public function testOversizedCandidatesAreSkippedEvenWhenTheirDistanceIsZeroOrOne(): void
    {
        $catalog = self::catalog([
            InputBoundsLength129Command::class,
            InputBoundsLength128Command::class,
        ]);
        $suggestor = new SmartSuggestor();
        $unknown = \str_repeat('a', 127) . 'b';
        self::assertSame(128, \strlen($unknown));
        self::assertSame(129, \strlen(InputBoundsLength129Command::NAME));
        self::assertSame(2, \levenshtein($unknown, InputBoundsLength129Command::NAME));
        self::assertSame([InputBoundsLength128Command::NAME], $suggestor->suggest($unknown, $catalog));
        self::assertSame([], $suggestor->suggest(InputBoundsLength129Command::NAME, $catalog));
    }

    public function testLengthDifferencePruningPreservesDistanceThenCatalogOrderRanking(): void
    {
        $catalog = self::catalog([
            InputBoundsFarCommand::class,
            InputBoundsFirstTieCommand::class,
            InputBoundsCloserCommand::class,
            InputBoundsSecondTieCommand::class,
            InputBoundsAnotherFarCommand::class,
        ]);
        $suggestor = new SmartSuggestor();
        $unknown = 'testcmx';
        self::assertSame([
            'testcmc',
            'testcma',
            'testcme',
        ], $suggestor->suggest($unknown, $catalog));
        self::assertSame(['testcmc', 'testcma', 'testcme'], $suggestor->suggest($unknown, $catalog));
        self::assertSame(1, \levenshtein($unknown, 'testcmc'));
        self::assertSame(1, \levenshtein($unknown, 'testcma'));
        self::assertSame(1, \levenshtein($unknown, 'testcme'));
        self::assertSame(3, \strlen('testcmx') - \strlen('test'));

        $sourcePath = new \ReflectionClass(SmartSuggestor::class)->getFileName();
        self::assertIsString($sourcePath);
        $source = \file_get_contents($sourcePath);
        self::assertIsString($source);
        $pruning = \strpos($source, '\\abs($candidateLength - $length) > $threshold');
        $comparison = \strpos($source, '\\levenshtein($unknownName, $candidate)');
        self::assertNotFalse($pruning);
        self::assertNotFalse($comparison);
        self::assertLessThan($comparison, $pruning, 'Length pruning must precede levenshtein().');
    }

    /** @param list<class-string<CommandInterface>> $classes */
    private static function catalog(array $classes): CommandCatalog
    {
        $registry = new TagRegistry();
        foreach ($classes as $class) {
            $registry->add(ReservedTags::CLI_COMMAND, $class, meta: [
                'name' => $class::NAME,
                'summary' => $class::SUMMARY,
                'group' => $class::GROUP,
                'hidden' => $class::HIDDEN,
                'arguments' => $class::ARGUMENTS,
                'options' => $class::OPTIONS,
            ]);
        }
        return new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
    }
}

/** @internal */
abstract class InputBoundsBaseCommand implements CommandInterface
{
    public const string NAME = 'testcmc';
    public const string SUMMARY = 'Bounded suggestion candidate.';
    public const string GROUP = 'probe';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct()
    {
        throw new \LogicException('SmartSuggestor may not resolve candidate services.');
    }

    public function name(): string
    {
        return static::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}

final class InputBoundsNearCommand extends InputBoundsBaseCommand
{
    public const string NAME = 'testcmc';
}

final class InputBoundsLength128Command extends InputBoundsBaseCommand
{
    public const string NAME = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
}

final class InputBoundsLength129Command extends InputBoundsBaseCommand
{
    public const string NAME = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
}

final class InputBoundsFarCommand extends InputBoundsBaseCommand
{
    public const string NAME = 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzz';
}

final class InputBoundsAnotherFarCommand extends InputBoundsBaseCommand
{
    public const string NAME = 'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy';
}

final class InputBoundsFirstTieCommand extends InputBoundsBaseCommand
{
    public const string NAME = 'testcma';
}

final class InputBoundsCloserCommand extends InputBoundsBaseCommand
{
    public const string NAME = 'testcmc';
}

final class InputBoundsSecondTieCommand extends InputBoundsBaseCommand
{
    public const string NAME = 'testcme';
}
