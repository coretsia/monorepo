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
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowExecutionProbeCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class WorkflowRunAggregatesOrderedStepRecordsTest extends TestCase
{
    protected function setUp(): void
    {
        WorkflowExecutionProbeCommand::reset();
    }

    public function testSuccessfulWorkflowPreservesEveryCanonicalChildAndExportsOneOrderedParentRecord(): void
    {
        $fixture = self::fixture(['first' => 'mixed', 'second' => 'mixed']);
        $direct = $fixture->workflowRunner->run($fixture->workflows->require('verify'), 'json');
        self::assertSame('success', $direct->outcome());
        self::assertSame(0, $direct->exitCode());
        self::assertCount(2, $direct->steps());

        foreach ($direct->steps() as $index => $step) {
            $subject = $index === 0 ? 'first' : 'second';
            self::assertSame($index, $step->index());
            self::assertSame(WorkflowExecutionProbeCommand::NAME, $step->command());
            self::assertSame(0, $step->exitCode());
            self::assertSame(self::childRecords($subject), $step->records());
        }

        $array = $direct->toArray();
        self::assertSame(['exit_code', 'outcome', 'steps', 'workflow'], \array_keys($array));
        self::assertSame('verify', $array['workflow']);
        self::assertSame([0, 1], \array_column($array['steps'], 'index'));
        foreach ($array['steps'] as $index => $step) {
            self::assertSame(['command', 'exit_code', 'index', 'records'], \array_keys($step));
            self::assertSame(['text', 'json', 'error'], \array_column($step['records'], 'type'));
            self::assertSame(['data', 'type'], \array_keys($step['records'][1]));
            self::assertSame(
                self::childRecords($index === 0 ? 'first' : 'second')[1]['payload'],
                $step['records'][1]['data'],
            );
            self::assertArrayNotHasKey('payload', $step['records'][1]);
        }

        WorkflowExecutionProbeCommand::reset();
        $output = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(0, $output['exit'], $output['stderr']);
        self::assertSame('', $output['stderr']);
        $document = WorkflowIntegrationFixture::document($output);
        self::assertSame('coretsia.cli-output@1', $document['schema']);
        self::assertSame(
            ['command' => WorkflowRunCommand::NAME, 'exit_code' => 0, 'outcome' => 'success'],
            $document['meta']
        );
        self::assertCount(1, $document['data']['records']);
        self::assertSame(['payload', 'type'], \array_keys($document['data']['records'][0]));
        self::assertSame($array, $document['data']['records'][0]['payload']);
        self::assertSame(['first', 'second'], \array_column(WorkflowExecutionProbeCommand::$calls, 'subject'));
        self::assertSame('', $output['stderr']);
    }

    public function testNonZeroStepProducesCompletedFailureWithoutSyntheticSubsequentSteps(): void
    {
        $fixture = self::fixture(['first' => 'mixed', 'stop' => 'fail', 'never' => 'mixed']);
        $output = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(17, $output['exit']);
        self::assertSame('', $output['stderr']);
        self::assertSame(['first', 'stop'], \array_column(WorkflowExecutionProbeCommand::$calls, 'subject'));
        self::assertSame(2, $fixture->runtime->calls);
        self::assertSame(2, \count(WorkflowExecutionProbeCommand::$calls));
        $records = WorkflowIntegrationFixture::document($output)['data']['records'];
        self::assertCount(1, $records);
        self::assertSame('json', $records[0]['type']);
        $payload = $records[0]['payload'];
        self::assertSame('failure', $payload['outcome']);
        self::assertSame(17, $payload['exit_code']);
        self::assertSame([0, 1], \array_column($payload['steps'], 'index'));
        self::assertSame([0, 17], \array_column($payload['steps'], 'exit_code'));
        self::assertSame(self::childRecords('stop')[1]['payload'], $payload['steps'][1]['records'][1]['data']);
        self::assertSame(['text', 'json', 'error'], \array_column($payload['steps'][1]['records'], 'type'));
    }

    /** @param array<string,string> $modes */
    private static function fixture(array $modes): WorkflowIntegrationFixture
    {
        $steps = [];
        foreach ($modes as $subject => $mode) {
            $steps[] = [
                'command' => WorkflowExecutionProbeCommand::NAME,
                'arguments' => [$subject],
                'options' => ['mode' => $mode],
            ];
        }
        return new WorkflowIntegrationFixture(
            ['verify' => ['summary' => 'Aggregate child records.', 'steps' => $steps]],
            withProbe: true,
        );
    }

    /** @return list<array<string,mixed>> */
    private static function childRecords(string $subject): array
    {
        return [
            ['text' => 'text-' . $subject, 'type' => 'text'],
            [
                'payload' => [
                    'label' => $subject,
                    'nested' => ['password' => 'PRIVATE_PASSWORD_' . $subject, 'safe' => 'visible-' . $subject],
                ],
                'type' => 'json',
            ],
            ['code' => 'PROBE_NOTICE', 'message' => 'notice-' . $subject, 'type' => 'error'],
        ];
    }
}
