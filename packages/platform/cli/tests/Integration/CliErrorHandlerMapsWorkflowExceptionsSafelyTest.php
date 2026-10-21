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

use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Diagnostics\CliErrorHandler;
use Coretsia\Platform\Cli\Exception\CliCommandFailedException;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Exception\WorkflowNotFoundException;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingOwnerCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class CliErrorHandlerMapsWorkflowExceptionsSafelyTest extends TestCase
{
    public function testDefinitionErrorsPreserveOnlyTheirAllowlistedCodeAndReason(): void
    {
        $handler = new CliErrorHandler();
        foreach (
            [
                WorkflowDefinitionInvalidException::REASON_DEFINITION_INVALID,
                WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN,
                WorkflowDefinitionInvalidException::REASON_COMMAND_COMPOSITE,
            ] as $reason
        ) {
            $exception = WorkflowDefinitionInvalidException::withReason($reason);
            $descriptor = $handler->handle($exception);
            self::assertSame(WorkflowDefinitionInvalidException::ERROR_CODE, $descriptor->code());
            self::assertSame($reason, $descriptor->message());
            self::assertSame([], $descriptor->extensions());
            self::assertSame(
                WorkflowDefinitionInvalidException::ERROR_CODE . ': ' . $reason,
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
        }
    }

    public function testNotFoundAndUnknownThrowablesCannotExposePrivateInputsOrTraces(): void
    {
        $handler = new CliErrorHandler();
        $exception = WorkflowNotFoundException::notFound();
        $descriptor = $handler->handle($exception);
        self::assertSame(WorkflowNotFoundException::ERROR_CODE, $descriptor->code());
        self::assertSame(WorkflowNotFoundException::REASON_WORKFLOW_NOT_FOUND, $descriptor->message());
        self::assertSame([], $descriptor->extensions());
        self::assertNull($exception->getPrevious());
        self::assertSame(WorkflowNotFoundException::ERROR_CODE . ': workflow-not-found', $exception->getMessage());

        $unsafe = new \RuntimeException(
            'PRIVATE_SECRET /home/private/path',
            0,
            new \LogicException('PRIVATE_PREVIOUS'),
        );
        $unknown = $handler->handle($unsafe);
        self::assertSame('CORETSIA_CLI_INTERNAL_ERROR', $unknown->code());
        self::assertSame('Internal CLI error.', $unknown->message());
        self::assertSame([], $unknown->extensions());
        self::assertStringNotContainsString('PRIVATE', $unknown->message());
        self::assertSame(
            CliInputInvalidException::REASON_COMMAND_UNKNOWN,
            $handler->handle(
                CliInputInvalidException::withReason(CliInputInvalidException::REASON_COMMAND_UNKNOWN),
            )->message(),
        );
    }

    public function testExistingNonWorkflowCliExceptionMappingsRemainUnchanged(): void
    {
        $handler = new CliErrorHandler();

        foreach (
            [
                CliInputInvalidException::withReason(CliInputInvalidException::REASON_COMMAND_UNKNOWN),
                CliConfigInvalidException::withReason(),
                InvalidCommandTagMetaException::withReason(),
                CliCommandFailedException::failed(),
                new KernelOpsFailedException(KernelOpsFailedException::REASON_OPERATION_FAILED),
            ] as $exception
        ) {
            $descriptor = $handler->handle($exception);

            self::assertSame($exception->errorCode(), $descriptor->code());
            self::assertSame($exception->reason(), $descriptor->message());
            self::assertSame([], $descriptor->extensions());
        }
    }

    public function testUnknownValidAndInvalidNamesUseTheSameSafeApplicationJsonBoundary(): void
    {
        foreach (['absent-workflow', 'PRIVATE_WORKFLOW_TOKEN', '../PRIVATE_PATH'] as $name) {
            $fixture = new WorkflowIntegrationFixture([]);
            $result = $fixture->run(['workflow:run', $name, '--format=json']);
            self::assertSame(1, $result['exit']);
            self::assertSame('', $result['stderr']);
            $document = WorkflowIntegrationFixture::document($result);
            self::assertSame('coretsia.cli-output@1', $document['schema']);
            self::assertSame('failure', $document['meta']['outcome']);
            self::assertSame(1, $document['meta']['exit_code']);
            self::assertSame([
                [
                    'code' => WorkflowNotFoundException::ERROR_CODE,
                    'message' => WorkflowNotFoundException::REASON_WORKFLOW_NOT_FOUND,
                    'type' => 'error',
                ],
            ], $document['data']['records']);
            self::assertStringNotContainsString($name, $result['stdout']);
            self::assertSame(0, $fixture->runtime->calls);
        }
    }

    public function testWorkflowDefinitionFailureDuringServiceResolutionUsesCanonicalJsonErrorBoundary(): void
    {
        $fixture = new WorkflowIntegrationFixture([
            'verify' => [
                'summary' => 'Valid definition.',
                'steps' => [
                    [
                        'command' => RecordingOwnerCommand::NAME,
                        'arguments' => [],
                        'options' => [],
                    ],
                ],
            ],
        ]);
        $fixture->container->failWorkflowResolution = true;

        $result = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stderr']);
        $document = WorkflowIntegrationFixture::document($result);
        self::assertSame('coretsia.cli-output@1', $document['schema']);
        self::assertSame([
            [
                'code' => WorkflowDefinitionInvalidException::ERROR_CODE,
                'message' => WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN,
                'type' => 'error',
            ],
        ], $document['data']['records']);
        self::assertSame(0, $fixture->runtime->calls);
        self::assertSame([WorkflowRunCommand::class], $fixture->container->resolved);
    }

    public function testDeepInvalidDefinitionsReachTheSameSafeApplicationErrorRenderer(): void
    {
        $fixture = new WorkflowIntegrationFixture([]);
        $result = $fixture->run(['workflow:run', 'unknown', '--format=plain']);
        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(WorkflowNotFoundException::ERROR_CODE . ': workflow-not-found' . "\n", $result['stderr']);
        self::assertSame(0, $fixture->runtime->calls);

        try {
            new WorkflowIntegrationFixture([
                'broken' => [
                    'summary' => 'Invalid.',
                    'steps' => [
                        ['command' => 'missing:private', 'arguments' => [], 'options' => []],
                    ],
                ],
            ]);
            self::fail('Invalid configured command must fail before workflow execution.');
        } catch (WorkflowDefinitionInvalidException $exception) {
            $descriptor = new CliErrorHandler()->handle($exception);
            self::assertSame(WorkflowDefinitionInvalidException::ERROR_CODE, $descriptor->code());
            self::assertSame(WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN, $descriptor->message());
            self::assertSame([], $descriptor->extensions());
            self::assertStringNotContainsString('missing:private', $exception->getMessage());
        }
    }
}
