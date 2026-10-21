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

final class SmartSuggestorTest extends TestCase
{
    public function testEightAndNineByteTokensUseTwoAndThreeEditsRespectively(): void
    {
        $catalog = self::catalog([
            SuggestionFLengthTenCommand::class,
            SuggestionGLengthElevenCommand::class,
            SuggestionHLengthTwelveCommand::class,
            SuggestionIShortCommand::class,
            SuggestionJLengthThirteenCommand::class,
        ]);
        $suggestor = new SmartSuggestor();

        self::assertSame(8, \strlen('abcdefgh'));
        self::assertSame(9, \strlen('abcdefghi'));
        self::assertSame(['abcdefghij', 'abcdef'], $suggestor->suggest('abcdefgh', $catalog));
        self::assertSame(['abcdefghij', 'abcdefghijk', 'abcdefghijkl'], $suggestor->suggest('abcdefghi', $catalog));
        self::assertSame(
            ['abcdefghijkl', 'abcdef'],
            $suggestor->suggest(
                'abcdefghi',
                self::catalog([SuggestionHLengthTwelveCommand::class, SuggestionIShortCommand::class]),
            )
        );
    }

    public function testCandidateLengthNeverChangesTheInvocationThreshold(): void
    {
        $catalog = self::catalog([
            SuggestionGLengthElevenCommand::class,
            SuggestionHLengthTwelveCommand::class,
            SuggestionIShortCommand::class,
        ]);
        $suggestor = new SmartSuggestor();

        self::assertSame(['abcdef'], $suggestor->suggest('abcdefgh', $catalog));
        self::assertSame(['abcdefghijk', 'abcdefghijkl', 'abcdef'], $suggestor->suggest('abcdefghi', $catalog));
        self::assertSame(3, \levenshtein('abcdefghi', 'abcdefghijkl'));
        self::assertSame(3, \levenshtein('abcdefghi', 'abcdef'));
        self::assertSame(3, \levenshtein('abcdefgh', 'abcdefghijk'));
    }

    public function testHiddenCommandsAreExcludedAndCatalogOrderWinsDistanceTies(): void
    {
        $catalog = self::catalog([
            SuggestionEFourthCommand::class,
            SuggestionDThirdCommand::class,
            SuggestionCHiddenCommand::class,
            SuggestionBSecondCommand::class,
            SuggestionAFirstCommand::class,
        ]);
        $expected = ['testcmc', 'testcma', 'testcme'];
        self::assertSame([
            'testcmc',
            'testcma',
            'testcmx',
            'testcme',
            'testcmb',
        ], \array_keys($catalog->descriptors()));
        $suggestor = new SmartSuggestor();
        $before = \array_keys($catalog->descriptors());

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            self::assertSame($expected, $suggestor->suggest('testcmx', $catalog));
        }
        self::assertSame($before, \array_keys($catalog->descriptors()));
        self::assertCount(3, $suggestor->suggest('testcmx', $catalog));
        self::assertNotContains('testcmx', $suggestor->suggest('testcmx', $catalog));
        self::assertNotContains('testcmb', $suggestor->suggest('testcmx', $catalog));
    }

    public function testMaximumLengthAndInvalidTokensFailClosed(): void
    {
        $catalog = self::catalog([
            SuggestionKLength128Command::class,
            SuggestionLLength129Command::class,
        ]);
        $suggestor = new SmartSuggestor();
        $exact = \str_repeat('a', 128);
        self::assertSame(128, \strlen($exact));
        self::assertSame(129, \strlen(SuggestionLLength129Command::NAME));
        self::assertSame([$exact], $suggestor->suggest($exact, $catalog));

        foreach (
            [
                '',
                'BadName',
                'unknown::name',
                'name with spaces',
                "bad\nname",
                "caf\xC3\xA9",
                \str_repeat('a', 129),
            ] as $invalid
        ) {
            self::assertSame([], $suggestor->suggest($invalid, $catalog));
        }

        $near = self::catalog([
            SuggestionAFirstCommand::class,
            SuggestionBSecondCommand::class,
        ]);

        foreach (['testcmX', 'testcm:', "testcm\n", "testcm\xC3\xA9"] as $invalid) {
            self::assertSame([], $suggestor->suggest($invalid, $near));
        }
    }

    public function testSuggestorHasNoInvocationCacheAndDoesNotResolveServices(): void
    {
        $catalog = self::catalog([SuggestionAFirstCommand::class, SuggestionBSecondCommand::class]);
        $suggestor = new SmartSuggestor();
        self::assertSame([], new \ReflectionClass(SmartSuggestor::class)->getProperties());
        self::assertSame(['testcmc', 'testcma'], $suggestor->suggest('testcmx', $catalog));
        self::assertSame(['testcmc', 'testcma'], $suggestor->suggest('testcmx', $catalog));
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
abstract class SuggestionFixtureCommand implements CommandInterface
{
    public const string NAME = 'fixture:base';
    public const string SUMMARY = 'Suggestion fixture command.';
    public const string GROUP = 'fixture';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct()
    {
        throw new \LogicException('suggestions-must-not-instantiate-command');
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

final class SuggestionAFirstCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'testcmc';
}

final class SuggestionBSecondCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'testcma';
}

final class SuggestionCHiddenCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'testcmx';
    public const bool HIDDEN = true;
}

final class SuggestionDThirdCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'testcme';
}

final class SuggestionEFourthCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'testcmb';
}

final class SuggestionFLengthTenCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'abcdefghij';
}

final class SuggestionGLengthElevenCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'abcdefghijk';
}

final class SuggestionHLengthTwelveCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'abcdefghijkl';
}

final class SuggestionIShortCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'abcdef';
}

final class SuggestionJLengthThirteenCommand extends SuggestionFixtureCommand
{
    public const string NAME = 'abcdefghijklm';
}

final class SuggestionKLength128Command extends SuggestionFixtureCommand
{
    public const string NAME = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
}

final class SuggestionLLength129Command extends SuggestionFixtureCommand
{
    public const string NAME = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
}
