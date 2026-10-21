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
use Coretsia\Platform\Cli\Exception\WorkflowNotFoundException;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use PHPUnit\Framework\TestCase;

final class WorkflowCatalogDeterminismTest extends TestCase
{
    public function testCanonicalNamesAndDeclaredStepOrderSurviveCatalogConstruction(): void
    {
        $raw = [
            'zeta' => self::definition(['probe:lazy']),
            'alpha-two' => self::definition(['probe:lazy', 'probe:lazy', 'probe:lazy']),
            'alpha' => self::definition(['probe:lazy']),
        ];
        $raw['alpha-two']['steps'][0]['arguments'] = ['second', 'first'];
        $raw['alpha-two']['steps'][1]['options'] = ['z-option' => ['third', 'first'], 'a-option' => true];
        $raw['alpha-two']['steps'][2]['arguments'] = ['last'];
        $definitions = new WorkflowSchema()->normalize($raw);
        $catalog = new WorkflowCatalog($definitions, self::commands());

        self::assertSame(['alpha', 'alpha-two', 'zeta'], \array_keys($catalog->definitions()));
        self::assertSame($definitions, $catalog->definitions());
        self::assertSame($definitions['alpha-two'], $catalog->require('alpha-two'));
        self::assertSame(
            ['probe:lazy', 'probe:lazy', 'probe:lazy'],
            \array_map(
                static fn ($step): string => $step->command(),
                $catalog->require('alpha-two')->steps(),
            )
        );
        self::assertSame(['second', 'first'], $catalog->require('alpha-two')->steps()[0]->arguments());
        self::assertSame(['a-option', 'z-option'], \array_keys($catalog->require('alpha-two')->steps()[1]->options()));
        self::assertSame(['third', 'first'], $catalog->require('alpha-two')->steps()[1]->options()['z-option']);
        self::assertSame(['last'], $catalog->require('alpha-two')->steps()[2]->arguments());
        self::assertSame(['zeta', 'alpha-two', 'alpha'], \array_keys($raw));
        self::assertSame($catalog->definitions(), new WorkflowCatalog($definitions, self::commands())->definitions());
    }

    public function testFinalCatalogConstructionAndLookupsNeverInstantiateCommandServices(): void
    {
        WorkflowCatalogLazyProbeCommand::$constructorCalls = 0;
        WorkflowCatalogLazyProbeCommand::$runCalls = 0;
        $commands = self::commands();
        $schema = new WorkflowSchema();
        $definitions = $schema->normalize(['verify' => self::definition(['probe:lazy', 'probe:lazy'])]);
        $catalog = new WorkflowCatalog($definitions, $commands);

        self::assertSame(0, WorkflowCatalogLazyProbeCommand::$constructorCalls);
        self::assertSame(0, WorkflowCatalogLazyProbeCommand::$runCalls);
        self::assertSame(['verify'], \array_keys($catalog->definitions()));
        self::assertSame('verify', $catalog->require('verify')->name());
        self::assertSame($catalog->require('verify'), $catalog->require('verify'));
        self::assertSame(0, WorkflowCatalogLazyProbeCommand::$constructorCalls);
        self::assertSame(0, WorkflowCatalogLazyProbeCommand::$runCalls);

        $copy = $catalog->definitions();
        unset($copy['verify']);
        self::assertSame(['verify'], \array_keys($catalog->definitions()));
    }

    public function testUnknownAndUnsafeLookupTokensHaveFixedNonDisclosingError(): void
    {
        $catalog = new WorkflowCatalog(new WorkflowSchema()->normalize([
            'verify' => self::definition(['probe:lazy']),
        ]), self::commands());

        foreach (['absent', 'Verify', 'a' . \str_repeat('b', 64), "bad\nname"] as $invalid) {
            try {
                $catalog->require($invalid);
                self::fail('Invalid or unknown workflow must be rejected.');
            } catch (WorkflowNotFoundException $exception) {
                self::assertSame(WorkflowNotFoundException::ERROR_CODE, $exception->errorCode());
                self::assertSame(WorkflowNotFoundException::REASON_WORKFLOW_NOT_FOUND, $exception->reason());
                self::assertStringNotContainsString($invalid, $exception->getMessage());
            }
        }
    }

    /** @param non-empty-list<string> $commands @return array<string, mixed> */
    private static function definition(array $commands): array
    {
        return [
            'summary' => 'A safe workflow summary.',
            'steps' => \array_map(static fn (string $command): array => [
                'command' => $command,
                'arguments' => [],
                'options' => [],
            ], $commands),
        ];
    }

    private static function commands(): CommandCatalog
    {
        $registry = new TagRegistry();
        $class = WorkflowCatalogLazyProbeCommand::class;
        $registry->add(ReservedTags::CLI_COMMAND, $class, meta: [
            'name' => $class::NAME,
            'summary' => $class::SUMMARY,
            'group' => $class::GROUP,
            'hidden' => $class::HIDDEN,
            'arguments' => $class::ARGUMENTS,
            'options' => $class::OPTIONS,
        ]);

        return new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
    }
}

final class WorkflowCatalogLazyProbeCommand implements CommandInterface
{
    public static int $constructorCalls = 0;
    public static int $runCalls = 0;
    public const string NAME = 'probe:lazy';
    public const string SUMMARY = 'Lazy command descriptor fixture.';
    public const string GROUP = 'probe';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct()
    {
        ++self::$constructorCalls;
        throw new \LogicException('workflow-catalog-must-not-construct-command');
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        ++self::$runCalls;
        return 0;
    }
}
