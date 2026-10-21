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
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use PHPUnit\Framework\TestCase;

final class WorkflowRunUsesSharedRepeatableOptionNormalizationTest extends TestCase
{
    public function testScalarRepeatableRequiredValueIsNormalizedExactlyOnceBeforeOwnerDispatch(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = self::fixture([self::step(['item' => 'first'])]);
        $result = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame(['item' => ['first']], RecordingOwnerCommand::$received['options']);
        self::assertSame(1, RecordingOwnerCommand::$constructed);
        self::assertSame(1, RecordingOwnerCommand::$executed);
        self::assertSame(1, $fixture->runtime->calls);
        self::assertSame('cli', $fixture->runtime->invocations[0]['type']);
        self::assertSame(
            ['operation' => RecordingOwnerCommand::NAME, 'output_format' => 'json'],
            $fixture->runtime->invocations[0]['attributes']
        );
        self::assertSame([WorkflowRunCommand::class, RecordingOwnerCommand::class], $fixture->container->resolved);
        self::assertSame(
            ['first'],
            WorkflowIntegrationFixture::document(
                $result,
            )['data']['records'][0]['payload']['steps'][0]['records'][0]['data']['items']
        );
    }

    public function testRepeatedValuesPreserveTheirDeclaredOrderAcrossMultipleSteps(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = self::fixture([
            self::step(['item' => ['third', 'first', 'second']]),
            self::step(['item' => 'fourth']),
        ]);
        $result = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame(2, $fixture->runtime->calls);
        self::assertSame(['cli', 'cli'], \array_column($fixture->runtime->invocations, 'type'));
        self::assertSame(2, RecordingOwnerCommand::$executed);
        $steps = WorkflowIntegrationFixture::document($result)['data']['records'][0]['payload']['steps'];
        self::assertSame(['third', 'first', 'second'], $steps[0]['records'][0]['data']['items']);
        self::assertSame(['fourth'], $steps[1]['records'][0]['data']['items']);
        self::assertSame([0, 1], \array_column($steps, 'index'));
    }

    public function testLaterStructuralValidationFailureStopsWithoutPartialRecordsOrAnotherUow(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = self::fixture([
            self::step(['item' => 'completed']),
            self::step(['unexpected' => 'PRIVATE_OPTION']),
            self::step(['item' => 'never']),
        ]);
        $result = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stderr']);
        $document = WorkflowIntegrationFixture::document($result);
        self::assertSame([
            [
                'code' => CliInputInvalidException::ERROR_CODE,
                'message' => CliInputInvalidException::REASON_OPTION_UNKNOWN,
                'type' => 'error',
            ],
        ], $document['data']['records']);
        self::assertSame(1, RecordingOwnerCommand::$constructed);
        self::assertSame(1, RecordingOwnerCommand::$executed);
        self::assertSame(1, $fixture->runtime->calls);
        self::assertSame([WorkflowRunCommand::class, RecordingOwnerCommand::class], $fixture->container->resolved);
        self::assertStringNotContainsString('completed', $result['stdout']);
        self::assertStringNotContainsString('PRIVATE_OPTION', $result['stdout']);
        self::assertStringNotContainsString('never', $result['stdout']);
    }

    public function testFirstStepStructuralValidationFailureResolvesNoOwnerService(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = self::fixture([self::step(['unknown' => true])]);
        $result = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(1, $result['exit']);
        self::assertSame([WorkflowRunCommand::class], $fixture->container->resolved);
        self::assertSame(0, $fixture->runtime->calls);
        self::assertSame(0, RecordingOwnerCommand::$constructed);
        self::assertSame(
            CliInputInvalidException::REASON_OPTION_UNKNOWN,
            WorkflowIntegrationFixture::document($result)['data']['records'][0]['message'],
        );
    }

    public function testValidationThrowablePropagatesUnchangedFromWorkflowRunner(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = self::fixture([
            self::step(['item' => 'finished']),
            self::step(['unexpected' => 'PRIVATE_OPTION']),
        ]);

        try {
            $fixture->workflowRunner->run($fixture->workflows->require('verify'), 'json');
            self::fail('Later structural input validation must propagate without completing a workflow.');
        } catch (CliInputInvalidException $exception) {
            self::assertSame(CliInputInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(CliInputInvalidException::REASON_OPTION_UNKNOWN, $exception->reason());
            self::assertSame(CliInputInvalidException::ERROR_CODE . ': cli-option-unknown', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(1, $fixture->runtime->calls);
        self::assertSame([RecordingOwnerCommand::class], $fixture->container->resolved);
        self::assertSame(1, RecordingOwnerCommand::$executed);
    }

    public function testRejectedStepCannotAllocateItsChildBufferBeforeInputValidation(): void
    {
        $method = new \ReflectionMethod(
            WorkflowRunner::class,
            'run',
        );
        $path = $method->getFileName();
        self::assertIsString($path);

        $lines = \file($path);
        self::assertTrue(\is_array($lines));

        $source = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );

        $validation = \strpos($source, '$this->validator->normalizeAndValidate(');
        $allocation = \strpos($source, 'new CommandOutputBuffer(');
        $dispatch = \strpos($source, '$this->runner->run(');

        self::assertTrue(\is_int($validation));
        self::assertTrue(\is_int($allocation));
        self::assertTrue(\is_int($dispatch));

        self::assertTrue($validation < $allocation);
        self::assertTrue($allocation < $dispatch);
    }

    /** @param list<array<string,mixed>> $steps */
    private static function fixture(array $steps): WorkflowIntegrationFixture
    {
        return new WorkflowIntegrationFixture(
            ['verify' => ['summary' => 'Repeatable owner options.', 'steps' => $steps]],
        );
    }

    /** @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function step(array $options): array
    {
        return ['command' => RecordingOwnerCommand::NAME, 'arguments' => [], 'options' => $options];
    }
}
