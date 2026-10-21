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
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkflowCatalogRejectsCompositeStepTest extends TestCase
{
    protected function setUp(): void
    {
        CompositeCatalogNormalProbeCommand::$constructorCalls = 0;
        CompositeCatalogNormalProbeCommand::$runCalls = 0;
    }

    #[DataProvider('compositeStepPositions')]
    public function testCompositeReferenceIsRejectedBeforeAnyNormalOrCompositeStepRuns(int $position): void
    {
        $commands = self::commands();
        self::assertSame('composite', $commands->require(WorkflowRunCommand::NAME)->executionKind());
        self::assertSame('normal', $commands->require(CompositeCatalogNormalProbeCommand::NAME)->executionKind());
        $steps = [self::step('probe:normal'), self::step('probe:normal'), self::step('probe:normal')];
        $steps[$position] = self::step(WorkflowRunCommand::NAME, ['PRIVATE_ARGUMENT'], [
            'private-option' => 'PRIVATE_VALUE',
        ]);
        $definitions = new WorkflowSchema()->normalize([
            'verify' => ['summary' => 'Validate all steps before dispatch.', 'steps' => $steps],
        ]);

        try {
            new WorkflowCatalog($definitions, $commands);
            self::fail('Composite workflow steps must fail before all command execution.');
        } catch (WorkflowDefinitionInvalidException $exception) {
            self::assertSame(WorkflowDefinitionInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(WorkflowDefinitionInvalidException::REASON_COMMAND_COMPOSITE, $exception->reason());
            self::assertSame(
                WorkflowDefinitionInvalidException::ERROR_CODE . ': ' . WorkflowDefinitionInvalidException::REASON_COMMAND_COMPOSITE,
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
            foreach (
                [
                    'workflow:run',
                    'PRIVATE_ARGUMENT',
                    'private-option',
                    'PRIVATE_VALUE',
                    WorkflowRunCommand::class,
                    'verify',
                ] as $private
            ) {
                self::assertStringNotContainsString($private, $exception->getMessage());
            }
        }
        self::assertSame(0, CompositeCatalogNormalProbeCommand::$constructorCalls);
        self::assertSame(0, CompositeCatalogNormalProbeCommand::$runCalls);
    }

    /** @return iterable<string, array{int}> */
    public static function compositeStepPositions(): iterable
    {
        yield 'first' => [0];
        yield 'middle' => [1];
        yield 'last' => [2];
    }

    public function testCatalogUsesTheDescriptorExecutionKindInsteadOfReclassifyingServiceIds(): void
    {
        $sourcePath = new \ReflectionClass(WorkflowCatalog::class)->getFileName();
        self::assertIsString($sourcePath);

        $source = \file_get_contents($sourcePath);
        self::assertIsString($source);
        self::assertStringContainsString('$descriptor->executionKind()', $source);
        self::assertStringNotContainsString('CommandTagSchema::isCompositeServiceId(', $source);
        self::assertStringNotContainsString('CommandRunner->run(', $source);
    }

    /** @return array<string, mixed> */
    private static function step(string $command, array $arguments = [], array $options = []): array
    {
        return ['command' => $command, 'arguments' => $arguments, 'options' => $options];
    }

    private static function commands(): CommandCatalog
    {
        $registry = new TagRegistry();
        foreach ([WorkflowRunCommand::class, CompositeCatalogNormalProbeCommand::class] as $class) {
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

final class CompositeCatalogNormalProbeCommand implements CommandInterface
{
    public static int $constructorCalls = 0;
    public static int $runCalls = 0;
    public const string NAME = 'probe:normal';
    public const string SUMMARY = 'Normal command reference without service resolution.';
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
