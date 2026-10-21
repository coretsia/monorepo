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

use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\CliOutputFormatException;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowExecutionProbeCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowIntegrationFixture;
use Coretsia\Platform\Cli\Workflow\WorkflowResult;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use Coretsia\Platform\Cli\Workflow\WorkflowStepResult;
use PHPUnit\Framework\TestCase;

final class WorkflowRunParentOutputBoundsFailClosedTest extends TestCase
{
    public function testValidIndividualChildOutputsFailOnlyAtExistingParentBudgetWithoutPartialRendering(): void
    {
        WorkflowExecutionProbeCommand::reset();
        $fixture = new WorkflowIntegrationFixture([
            'verify' => [
                'summary' => 'Large aggregate must fail closed.',
                'steps' => [
                    [
                        'command' => WorkflowExecutionProbeCommand::NAME,
                        'arguments' => ['first'],
                        'options' => ['mode' => 'large'],
                    ],
                    [
                        'command' => WorkflowExecutionProbeCommand::NAME,
                        'arguments' => ['second'],
                        'options' => ['mode' => 'large'],
                    ],
                ],
            ],
        ], withProbe: true);

        $direct = $fixture->workflowRunner->run($fixture->workflows->require('verify'), 'json');
        self::assertInstanceOf(WorkflowResult::class, $direct);
        self::assertSame(0, $direct->exitCode());
        self::assertCount(2, $direct->steps());
        self::assertSame(['first', 'second'], \array_column(WorkflowExecutionProbeCommand::$calls, 'subject'));
        foreach ($direct->steps() as $step) {
            self::assertCount(1, $step->records());
            self::assertSame(580000, \strlen($step->records()[0]['text']));
        }
        $parent = new CommandOutputBuffer();
        try {
            $parent->json($direct->toArray());
            self::fail('Combined normalized child output must exceed the existing parent output budget.');
        } catch (CliOutputFormatException) {
            // Existing parent output budget rejects the complete payload.
        }

        WorkflowExecutionProbeCommand::reset();
        $actual = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(1, $actual['exit']);
        self::assertSame('', $actual['stderr']);
        self::assertSame(2, \count(WorkflowExecutionProbeCommand::$calls));
        self::assertSame(4, $fixture->runtime->calls);
        $document = WorkflowIntegrationFixture::document($actual);
        self::assertSame('coretsia.cli-output@1', $document['schema']);
        self::assertSame(1, $document['meta']['exit_code']);
        self::assertCount(1, $document['data']['records']);
        self::assertSame('error', $document['data']['records'][0]['type']);
        self::assertStringNotContainsString(\str_repeat('L', 100), $actual['stdout']);
        self::assertStringNotContainsString('text-first', $actual['stdout']);
    }

    public function testWorkflowDoesNotImplementAnIndependentOutputBudgetOrFinalFormatter(): void
    {
        foreach (
            [
                WorkflowRunner::class,
                WorkflowResult::class,
                WorkflowStepResult::class,
                WorkflowRunCommand::class,
            ] as $class
        ) {
            $path = new \ReflectionClass($class)->getFileName();
            self::assertIsString($path);
            $source = \file_get_contents($path);
            self::assertIsString($source);

            foreach (
                [
                    'JsonLikeNormalizationLimits',
                    'new OutputFormatter(',
                    'new JsonFormatter(',
                    'new ConsoleOutputWriter(',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }

        $path = new \ReflectionClass(WorkflowRunCommand::class)->getFileName();
        self::assertIsString($path);
        $source = \file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('$output->json($result->toArray());', $source);
    }
}
