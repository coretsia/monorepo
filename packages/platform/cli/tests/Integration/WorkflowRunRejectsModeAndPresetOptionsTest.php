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
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingOwnerCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class WorkflowRunRejectsModeAndPresetOptionsTest extends TestCase
{
    public function testWorkflowCommandOwnsOnlyOneRequiredArgumentAndNoOptions(): void
    {
        self::assertSame([
            [
                'name' => 'workflow',
                'summary' => 'Workflow name.',
                'required' => true,
                'variadic' => false,
            ],
        ], WorkflowRunCommand::ARGUMENTS);
        self::assertSame([], WorkflowRunCommand::OPTIONS);
        $fixture = self::fixture();
        self::assertSame([], $fixture->commands->require(WorkflowRunCommand::NAME)->options());
    }

    public function testModePresetAndTopLevelTargetFailBeforeCompositeResolution(): void
    {
        foreach (['--mode=enterprise', '--preset=console', '--target=api'] as $option) {
            RecordingOwnerCommand::reset();
            $fixture = self::fixture();
            $result = $fixture->run(['workflow:run', 'verify', $option, '--format=json']);
            self::assertSame(1, $result['exit']);
            self::assertSame('', $result['stderr']);
            $document = WorkflowIntegrationFixture::document($result);
            self::assertSame('coretsia.cli-output@1', $document['schema']);
            self::assertSame([
                [
                    'code' => CliInputInvalidException::ERROR_CODE,
                    'message' => CliInputInvalidException::REASON_OPTION_UNKNOWN,
                    'type' => 'error',
                ],
            ], $document['data']['records']);
            self::assertSame([], $fixture->container->resolved);
            self::assertSame(0, $fixture->runtime->calls);
            self::assertSame(0, RecordingOwnerCommand::$constructed);
            self::assertSame(0, RecordingOwnerCommand::$executed);
            self::assertStringNotContainsString('enterprise', $result['stdout']);
        }
    }

    public function testGlobalFormatAndColorRemainSeparateFromOwnerOptions(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = self::fixture();
        $result = $fixture->run(['workflow:run', 'verify', '--format=json', '--color=never']);
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame('coretsia.cli-output@1', WorkflowIntegrationFixture::document($result)['schema']);
        self::assertSame([], RecordingOwnerCommand::$received['options']);
        self::assertSame(1, $fixture->runtime->calls);
        $plain = self::fixture()->run(['workflow:run', 'verify', '--format=plain', '--color=never']);
        self::assertSame(0, $plain['exit'], $plain['stderr']);
        self::assertSame('', $plain['stderr']);
        self::assertStringContainsString('verify', $plain['stdout']);
        self::assertSame([
            WorkflowRunCommand::class,
            RecordingOwnerCommand::class,
        ], $fixture->container->resolved);
    }

    private static function fixture(): WorkflowIntegrationFixture
    {
        return new WorkflowIntegrationFixture([
            'verify' => [
                'summary' => 'Validate options.',
                'steps' => [
                    ['command' => RecordingOwnerCommand::NAME, 'arguments' => [], 'options' => []],
                ],
            ],
        ]);
    }
}
