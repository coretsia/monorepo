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
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand\ExternalModeCommand;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use PHPUnit\Framework\TestCase;

final class WorkflowCatalogRejectsUnknownCommandTest extends TestCase
{
    protected function setUp(): void
    {
        UnknownCatalogKnownProbeCommand::$constructorCalls = 0;
        UnknownCatalogKnownProbeCommand::$runCalls = 0;
    }

    public function testUnknownReferenceInCompleteDefinitionMapFailsBeforeAnyStepIsDispatched(): void
    {
        $secretArgument = 'PRIVATE_STEP_ARGUMENT';
        $secretOption = 'PRIVATE_OPTION_VALUE';
        $definitions = new WorkflowSchema()->normalize([
            'zeta' => [
                'summary' => 'Later workflow remains validated.',
                'steps' => [
                    self::step('probe:known'),
                    self::step('external:mode', [$secretArgument], ['private-option' => $secretOption]),
                    self::step('probe:known'),
                ],
            ],
            'alpha' => [
                'summary' => 'Earlier workflow is never dispatched.',
                'steps' => [self::step('probe:known')],
            ],
        ]);
        self::assertSame(['alpha', 'zeta'], \array_keys($definitions));

        try {
            new WorkflowCatalog($definitions, self::commands(false));
            self::fail('All configured references must be resolved before any step executes.');
        } catch (WorkflowDefinitionInvalidException $exception) {
            self::assertSame(WorkflowDefinitionInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN, $exception->reason());
            self::assertSame(
                WorkflowDefinitionInvalidException::ERROR_CODE . ': ' . WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN,
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
            foreach (
                [
                    'external:mode',
                    $secretArgument,
                    'private-option',
                    $secretOption,
                    UnknownCatalogKnownProbeCommand::class,
                    'alpha',
                    'zeta',
                ] as $private
            ) {
                self::assertStringNotContainsString($private, $exception->getMessage());
            }
        }
        self::assertSame(0, UnknownCatalogKnownProbeCommand::$constructorCalls);
        self::assertSame(0, UnknownCatalogKnownProbeCommand::$runCalls);
    }

    public function testOnlyFinalCommandCatalogDeterminesWhetherAReferenceExists(): void
    {
        $definitions = new WorkflowSchema()->normalize([
            'verify' => [
                'summary' => 'Verify against final command catalog.',
                'steps' => [self::step('external:mode'), self::step('probe:known')],
            ],
        ]);
        $without = self::commands(false);
        self::assertNull($without->find('external:mode'));

        try {
            new WorkflowCatalog($definitions, $without);
            self::fail('The absent command must fail catalog construction.');
        } catch (WorkflowDefinitionInvalidException $exception) {
            self::assertSame(WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN, $exception->reason());
        }

        $with = self::commands(true);
        self::assertSame(ExternalModeCommand::class, $with->require('external:mode')->serviceId());
        $workflows = new WorkflowCatalog($definitions, $with);
        self::assertSame(['verify'], \array_keys($workflows->definitions()));
        self::assertSame(
            ['external:mode', 'probe:known'],
            \array_map(
                static fn ($step): string => $step->command(),
                $workflows->require('verify')->steps(),
            )
        );
        self::assertSame(0, UnknownCatalogKnownProbeCommand::$constructorCalls);
        self::assertSame(0, UnknownCatalogKnownProbeCommand::$runCalls);
    }

    /**
     * @param list<string> $arguments
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function step(string $command, array $arguments = [], array $options = []): array
    {
        return ['command' => $command, 'arguments' => $arguments, 'options' => $options];
    }

    private static function commands(bool $includeExternal): CommandCatalog
    {
        $registry = new TagRegistry();
        $classes = [UnknownCatalogKnownProbeCommand::class];
        if ($includeExternal) {
            $classes[] = ExternalModeCommand::class;
        }
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

final class UnknownCatalogKnownProbeCommand implements CommandInterface
{
    public static int $constructorCalls = 0;
    public static int $runCalls = 0;
    public const string NAME = 'probe:known';
    public const string SUMMARY = 'Known command reference without service resolution.';
    public const string GROUP = 'probe';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct()
    {
        ++self::$constructorCalls;
        throw new \LogicException('catalog-must-not-resolve-command-services');
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
