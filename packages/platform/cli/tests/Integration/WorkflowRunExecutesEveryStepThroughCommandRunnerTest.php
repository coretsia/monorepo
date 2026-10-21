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
use Coretsia\Platform\Cli\Exception\CliCommandFailedException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowExecutionProbeCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowIntegrationFixture;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class WorkflowRunExecutesEveryStepThroughCommandRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        WorkflowExecutionProbeCommand::reset();
    }

    public function testEveryStepUsesTheNormalRunnerWithNormalizedInputFreshOutputAndInheritedFormat(): void
    {
        $fixture = self::fixture(['first', 'second']);
        self::assertSame([], $fixture->container->resolved);
        self::assertSame(0, WorkflowExecutionProbeCommand::$constructed);
        $descriptor = $fixture->commands->require(WorkflowExecutionProbeCommand::NAME);
        self::assertSame('normal', $descriptor->executionKind());
        self::assertSame(WorkflowExecutionProbeCommand::class, $descriptor->serviceId());
        $result = $fixture->run(['workflow:run', 'verify', '--format=plain', '--color=never']);

        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(['first', 'second'], \array_column(WorkflowExecutionProbeCommand::$calls, 'subject'));
        self::assertSame(2, $fixture->runtime->calls);
        self::assertSame(['enter', 'leave', 'enter', 'leave'], $fixture->runtime->events);
        self::assertSame(['cli', 'cli'], \array_column($fixture->runtime->invocations, 'type'));
        self::assertSame([
            ['operation' => WorkflowExecutionProbeCommand::NAME, 'output_format' => 'plain'],
            ['operation' => WorkflowExecutionProbeCommand::NAME, 'output_format' => 'plain'],
        ], \array_column($fixture->runtime->invocations, 'attributes'));
        self::assertSame([
            WorkflowRunCommand::class,
            WorkflowExecutionProbeCommand::class,
            WorkflowExecutionProbeCommand::class,
        ], $fixture->container->resolved);

        foreach (WorkflowExecutionProbeCommand::$calls as $call) {
            self::assertInstanceOf(CommandOutputBuffer::class, $call['output']);
            self::assertSame(['item' => [$call['subject']], 'mode' => 'mixed'], $call['input']->options());
            self::assertSame([$call['subject']], $call['input']->arguments());
            self::assertSame([
                WorkflowExecutionProbeCommand::NAME,
                '--item=' . $call['subject'],
                '--mode=mixed',
                '--',
                $call['subject'],
            ], $call['input']->tokens());
            self::assertSame(WorkflowExecutionProbeCommand::NAME, $call['input']->commandName());
            try {
                $call['output']->finalize();
                self::fail('A successful child output buffer must already be finalized.');
            } catch (\Coretsia\Platform\Cli\Exception\CliOutputFormatException) {
                // Finalization is owned by the workflow runner and occurs exactly once.
            }
        }
        self::assertNotSame(
            WorkflowExecutionProbeCommand::$calls[0]['input'],
            WorkflowExecutionProbeCommand::$calls[1]['input'],
        );
        self::assertNotSame(
            WorkflowExecutionProbeCommand::$calls[0]['output'],
            WorkflowExecutionProbeCommand::$calls[1]['output'],
        );
    }

    public function testEachStepInheritsTheEffectiveJsonAndTableFormat(): void
    {
        foreach (['json', 'table'] as $format) {
            WorkflowExecutionProbeCommand::reset();
            $fixture = self::fixture(['first', 'second']);
            $result = $fixture->run(['workflow:run', 'verify', '--format=' . $format, '--color=never']);

            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame('', $result['stderr']);
            self::assertSame(
                [$format, $format],
                \array_column(
                    \array_column($fixture->runtime->invocations, 'attributes'),
                    'output_format',
                )
            );
            self::assertSame(
                ['first', 'second'],
                \array_column(WorkflowExecutionProbeCommand::$calls, 'subject'),
            );
        }
    }

    public function testExistingCommandRunnerIdentityAndExitCodeValidationCannotBeBypassed(): void
    {
        $invalidName = self::fixture(['first']);
        WorkflowExecutionProbeCommand::$wrongName = true;
        $nameResult = $invalidName->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(1, $nameResult['exit']);
        self::assertSame(0, \count(WorkflowExecutionProbeCommand::$calls));
        self::assertSame(1, $invalidName->runtime->calls);
        self::assertSame(
            InvalidCommandTagMetaException::ERROR_CODE,
            WorkflowIntegrationFixture::document($nameResult)['data']['records'][0]['code'],
        );

        WorkflowExecutionProbeCommand::reset();
        $invalidExit = self::fixture(['first'], 'invalid-exit');
        $exitResult = $invalidExit->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(1, $exitResult['exit']);
        self::assertSame(1, $invalidExit->runtime->calls);
        self::assertSame(
            CliCommandFailedException::ERROR_CODE,
            WorkflowIntegrationFixture::document($exitResult)['data']['records'][0]['code'],
        );
        self::assertSame(1, \count(WorkflowExecutionProbeCommand::$calls));

        WorkflowExecutionProbeCommand::reset();
        $invalidIdentity = self::fixture(['first']);
        $invalidIdentity->container->invalidProbeService = true;
        $identityResult = $invalidIdentity->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(1, $identityResult['exit']);
        self::assertSame(1, $invalidIdentity->runtime->calls);
        self::assertSame(0, WorkflowExecutionProbeCommand::$constructed);
        self::assertSame(
            InvalidCommandTagMetaException::ERROR_CODE,
            WorkflowIntegrationFixture::document($identityResult)['data']['records'][0]['code'],
        );
    }

    public function testNormalCommandRunnerOwnsStepLifecycleObservability(): void
    {
        $logger = new class() extends NullLogger {
            /** @var list<array{message:string,context:array<string,mixed>}> */
            public array $entries = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->entries[] = ['message' => (string) $message, 'context' => $context];
            }
        };
        $fixture = new WorkflowIntegrationFixture([
            'verify' => [
                'summary' => 'Observability owned by CommandRunner.',
                'steps' => [
                    ['command' => WorkflowExecutionProbeCommand::NAME, 'arguments' => ['one'], 'options' => []],
                    ['command' => WorkflowExecutionProbeCommand::NAME, 'arguments' => ['two'], 'options' => []],
                ],
            ],
        ], withProbe: true, logger: $logger);
        $output = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(0, $output['exit'], $output['stderr']);
        self::assertSame(['cli.command', 'cli.command', 'cli.command'], \array_column($logger->entries, 'message'));
        self::assertSame([
            WorkflowExecutionProbeCommand::NAME,
            WorkflowExecutionProbeCommand::NAME,
            WorkflowRunCommand::NAME,
        ], \array_column(\array_column($logger->entries, 'context'), 'operation'));
        foreach ($logger->entries as $entry) {
            self::assertSame(['operation', 'outcome', 'exit_code'], \array_keys($entry['context']));
        }
    }

    public function testWorkflowOrchestratorDoesNotOwnCommandExecutionOrKernelObservability(): void
    {
        $path = new \ReflectionClass(WorkflowRunner::class)->getFileName();
        self::assertIsString($path);
        $source = \file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('$this->validator->normalizeAndValidate(', $source);
        self::assertStringContainsString('$this->runner->run(', $source);
        foreach (
            [
                '->runUnitOfWork(',
                '->beginUnitOfWork(',
                '->afterUnitOfWork(',
                '->get(',
                '->startSpan(',
                '->increment(',
                '->observe(',
                '->log(',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    /** @param list<string> $subjects */
    private static function fixture(array $subjects, string $mode = 'mixed'): WorkflowIntegrationFixture
    {
        return new WorkflowIntegrationFixture([
            'verify' => [
                'summary' => 'Owner runner integration.',
                'steps' => \array_map(static fn (string $subject): array => [
                    'command' => WorkflowExecutionProbeCommand::NAME,
                    'arguments' => [$subject],
                    'options' => ['item' => $subject, 'mode' => $mode],
                ], $subjects),
            ],
        ], withProbe: true);
    }
}
