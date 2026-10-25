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

use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Platform\Cli\Exception\CliCommandFailedException;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Exception\WorkflowNotFoundException;
use Coretsia\Platform\Cli\Mapper\CliExceptionMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CliExceptionMapperTest extends TestCase
{
    #[DataProvider('knownExceptions')]
    public function testAllSevenAllowlistedTypesPreserveTheExactPublicCodeAndReason(
        \Throwable $failure,
        string $expectedCode,
        string $expectedReason,
    ): void {
        $context = new ErrorHandlingContext('cli', null, ['public_marker' => 'caller-only']);
        $mapper = new CliExceptionMapper();
        self::assertInstanceOf(ExceptionMapperInterface::class, $mapper);

        $descriptor = $mapper->map($failure, $context);
        self::assertInstanceOf(ErrorDescriptor::class, $descriptor);
        self::assertSame([
            'code' => $expectedCode,
            'extensions' => [],
            'httpStatus' => null,
            'message' => $expectedReason,
            'schemaVersion' => ErrorDescriptor::SCHEMA_VERSION,
            'severity' => ErrorSeverity::Error->value,
        ], $descriptor->toArray());
        self::assertSame($expectedCode, $failure->errorCode());
        self::assertSame($expectedReason, $failure->reason());
        self::assertSame(['public_marker' => 'caller-only'], $context->metadata());
        self::assertSame([], $descriptor->extensions());
    }

    /** @return iterable<string, array{\Throwable, string, string}> */
    public static function knownExceptions(): iterable
    {
        yield 'cli-input' => [
            CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_UNKNOWN),
            'CORETSIA_CLI_INPUT_INVALID',
            'cli-option-unknown',
        ];
        yield 'cli-config' => [
            CliConfigInvalidException::withReason(CliConfigInvalidException::REASON_OUTPUT_POLICY_INVALID),
            'CORETSIA_CLI_CONFIG_INVALID',
            'cli-output-policy-invalid',
        ];
        yield 'command-tag' => [
            InvalidCommandTagMetaException::withReason(InvalidCommandTagMetaException::REASON_NAME_COLLISION),
            'CORETSIA_CLI_INVALID_COMMAND_META',
            'command-name-collision',
        ];
        yield 'command-failure' => [
            CliCommandFailedException::failed(),
            'CORETSIA_CLI_COMMAND_FAILED',
            'cli-command-failed',
        ];
        yield 'workflow-definition' => [
            WorkflowDefinitionInvalidException::withReason(WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN),
            'CORETSIA_CLI_WORKFLOW_DEFINITION_INVALID',
            'workflow-command-unknown',
        ];
        yield 'workflow-not-found' => [
            WorkflowNotFoundException::notFound(),
            'CORETSIA_CLI_WORKFLOW_NOT_FOUND',
            'workflow-not-found',
        ];
        yield 'kernel-ops' => [
            new KernelOpsFailedException(KernelOpsFailedException::REASON_HOST_BOOT_FAILED),
            'CORETSIA_KERNEL_OPS_FAILED',
            'host-boot-failed',
        ];
    }

    public function testUnknownThrowableTypesReturnNullInsteadOfClaimingTheFallback(): void
    {
        $mapper = new CliExceptionMapper();
        $previous = new \RuntimeException('PRIVATE_PREVIOUS');

        foreach (
            [
                new \RuntimeException('PRIVATE_UNKNOWN', 0, $previous),
                new \LogicException('PRIVATE_LOGIC'),
                new \TypeError('PRIVATE_TYPE'),
                new \Error('PRIVATE_ENGINE'),
            ] as $throwable
        ) {
            self::assertNull($mapper->map($throwable));
            self::assertNull($mapper->map($throwable, new ErrorHandlingContext('cli')));
        }
    }

    public function testMapperNeverCopiesExceptionMessagesPreviousThrowablesOrCallerMetadata(): void
    {
        $mapper = new CliExceptionMapper();
        $context = new ErrorHandlingContext('cli', null, [
            'marker' => 'PRIVATE_CONTEXT_SENTINEL',
        ]);

        $rawMessage = new \ReflectionProperty(\Exception::class, 'message');
        $previousException = new \ReflectionProperty(\Exception::class, 'previous');

        foreach (self::knownExceptions() as [$failure, $code, $reason]) {
            $rawMessage->setValue($failure, 'PRIVATE_RAW_MESSAGE_SENTINEL');
            $previousException->setValue($failure, new \RuntimeException('PRIVATE_PREVIOUS_SENTINEL'));

            self::assertSame('PRIVATE_RAW_MESSAGE_SENTINEL', $failure->getMessage());
            self::assertSame('PRIVATE_PREVIOUS_SENTINEL', $failure->getPrevious()?->getMessage());

            $descriptor = $mapper->map($failure, $context);
            self::assertInstanceOf(ErrorDescriptor::class, $descriptor);
            $serialized = \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR);
            self::assertSame($reason, $descriptor->message());
            self::assertNotSame($failure->getMessage(), $descriptor->message());
            self::assertStringNotContainsString('PRIVATE_RAW_MESSAGE_SENTINEL', $serialized);
            self::assertStringNotContainsString('PRIVATE_PREVIOUS_SENTINEL', $serialized);
            self::assertStringNotContainsString('PRIVATE_CONTEXT_SENTINEL', $serialized);
            self::assertStringNotContainsString('Stack trace', $serialized);
            self::assertStringNotContainsString(__FILE__, $serialized);
            self::assertSame(
                ['code', 'extensions', 'httpStatus', 'message', 'schemaVersion', 'severity'],
                \array_keys($descriptor->toArray())
            );
            self::assertSame($code, $descriptor->code());
            self::assertNull($descriptor->httpStatus());
        }
    }

    public function testMapperIsStatelessAndHasNoRedactionReportingRenderingOrTransportDependencies(): void
    {
        $reflection = new \ReflectionClass(CliExceptionMapper::class);
        self::assertTrue($reflection->isFinal());
        self::assertSame([], $reflection->getProperties());
        self::assertNull($reflection->getConstructor());
        $source = \file_get_contents($reflection->getFileName());
        self::assertIsString($source);
        foreach (
            [
                'SensitiveDataRedactor',
                'RedactionContext',
                'ErrorReporter',
                'ExceptionRenderer',
                'OutputInterface',
                'Psr\\Http',
                '->report(',
                '->redact',
                '->render(',
                'getMessage(',
                'getPrevious(',
                'getTrace(',
                'getFile(',
                'getLine(',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }
}
