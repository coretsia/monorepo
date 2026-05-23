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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkContext;
use Coretsia\Kernel\Runtime\UnitOfWorkResult;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

final class UnitOfWorkResultShapeContractTest extends TestCase
{
    public function testConstructorShapeIsStable(): void
    {
        $reflection = new ReflectionClass(UnitOfWorkResult::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(9, $parameters);
        self::assertSame(7, $constructor->getNumberOfRequiredParameters());

        self::assertSame('uowId', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], 'string', false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertSame('type', $parameters[1]->getName());
        self::assertParameterNamedType($parameters[1], 'string', false);
        self::assertFalse($parameters[1]->isDefaultValueAvailable());

        self::assertSame('correlationId', $parameters[2]->getName());
        self::assertParameterNamedType($parameters[2], 'string', false);
        self::assertFalse($parameters[2]->isDefaultValueAvailable());

        self::assertSame('startedAt', $parameters[3]->getName());
        self::assertParameterNamedType($parameters[3], 'int', false);
        self::assertFalse($parameters[3]->isDefaultValueAvailable());

        self::assertSame('finishedAt', $parameters[4]->getName());
        self::assertParameterNamedType($parameters[4], 'int', false);
        self::assertFalse($parameters[4]->isDefaultValueAvailable());

        self::assertSame('durationMs', $parameters[5]->getName());
        self::assertParameterNamedType($parameters[5], 'int', false);
        self::assertFalse($parameters[5]->isDefaultValueAvailable());

        self::assertSame('outcome', $parameters[6]->getName());
        self::assertParameterNamedType($parameters[6], 'string', false);
        self::assertFalse($parameters[6]->isDefaultValueAvailable());

        self::assertSame('error', $parameters[7]->getName());
        self::assertParameterNamedType($parameters[7], ErrorDescriptor::class, true);
        self::assertTrue($parameters[7]->isDefaultValueAvailable());
        self::assertNull($parameters[7]->getDefaultValue());

        self::assertSame('extensions', $parameters[8]->getName());
        self::assertParameterNamedType($parameters[8], 'array', false);
        self::assertTrue($parameters[8]->isDefaultValueAvailable());
        self::assertSame([], $parameters[8]->getDefaultValue());
    }

    public function testGettersAndExportShapeWithoutErrorAreStable(): void
    {
        $result = new UnitOfWorkResult(
            uowId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            type: UnitOfWorkType::CLI,
            correlationId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
            startedAt: 1_710_000_000_123,
            finishedAt: 1_710_000_000_456,
            durationMs: 333,
            outcome: Outcome::SUCCESS,
            extensions: [
                'zeta' => 'last',
                'alpha' => [
                    'z' => 'nested-last',
                    'a' => 'nested-first',
                ],
                'items' => [
                    [
                        'b' => 2,
                        'a' => 1,
                    ],
                    'kept-in-list-order',
                ],
            ],
        );

        self::assertSame('01HV7N3ZJ5P8K7Y6T4R3Q2P1N0', $result->uowId());
        self::assertSame(UnitOfWorkType::CLI, $result->type());
        self::assertSame('01HV7N3ZJ5P8K7Y6T4R3Q2P1N1', $result->correlationId());
        self::assertSame(1_710_000_000_123, $result->startedAt());
        self::assertSame(1_710_000_000_456, $result->finishedAt());
        self::assertSame(333, $result->durationMs());
        self::assertSame(Outcome::SUCCESS, $result->outcome());
        self::assertNull($result->error());

        self::assertSame(
            [
                'alpha' => [
                    'a' => 'nested-first',
                    'z' => 'nested-last',
                ],
                'items' => [
                    [
                        'a' => 1,
                        'b' => 2,
                    ],
                    'kept-in-list-order',
                ],
                'zeta' => 'last',
            ],
            $result->extensions(),
        );

        self::assertSame(
            [
                'correlationId' => '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
                'durationMs' => 333,
                'extensions' => [
                    'alpha' => [
                        'a' => 'nested-first',
                        'z' => 'nested-last',
                    ],
                    'items' => [
                        [
                            'a' => 1,
                            'b' => 2,
                        ],
                        'kept-in-list-order',
                    ],
                    'zeta' => 'last',
                ],
                'finishedAt' => 1_710_000_000_456,
                'outcome' => Outcome::SUCCESS,
                'startedAt' => 1_710_000_000_123,
                'type' => UnitOfWorkType::CLI,
                'uowId' => '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            ],
            $result->toArray(),
        );

        self::assertSame(
            [
                'correlationId',
                'durationMs',
                'extensions',
                'finishedAt',
                'outcome',
                'startedAt',
                'type',
                'uowId',
            ],
            \array_keys($result->toArray()),
        );

        self::assertArrayNotHasKey(
            'error',
            $result->toArray(),
            'The exported result shape must omit error when no error exists.',
        );
    }

    public function testExportShapeWithErrorNormalizesErrorDescriptorToJsonLikeMap(): void
    {
        $error = new ErrorDescriptor(
            code: 'kernel.uow_failed',
            message: 'Unit of work failed.',
            severity: ErrorSeverity::Warning,
            httpStatus: 500,
            extensions: [
                'zeta' => 'last',
                'alpha' => 'first',
            ],
        );

        $result = new UnitOfWorkResult(
            uowId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            type: UnitOfWorkType::HTTP,
            correlationId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
            startedAt: 1_710_000_000_123,
            finishedAt: 1_710_000_000_456,
            durationMs: 333,
            outcome: Outcome::HANDLED_ERROR,
            error: $error,
            extensions: [
                'operation' => 'request',
            ],
        );

        self::assertSame(
            $error,
            $result->error(),
            'The runtime object may keep ErrorDescriptor internally.',
        );

        self::assertSame(
            [
                'correlationId' => '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
                'durationMs' => 333,
                'error' => [
                    'code' => 'kernel.uow_failed',
                    'extensions' => [
                        'alpha' => 'first',
                        'zeta' => 'last',
                    ],
                    'httpStatus' => 500,
                    'message' => 'Unit of work failed.',
                    'schemaVersion' => 1,
                    'severity' => 'warning',
                ],
                'extensions' => [
                    'operation' => 'request',
                ],
                'finishedAt' => 1_710_000_000_456,
                'outcome' => Outcome::HANDLED_ERROR,
                'startedAt' => 1_710_000_000_123,
                'type' => UnitOfWorkType::HTTP,
                'uowId' => '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            ],
            $result->toArray(),
        );

        self::assertSame(
            [
                'correlationId',
                'durationMs',
                'error',
                'extensions',
                'finishedAt',
                'outcome',
                'startedAt',
                'type',
                'uowId',
            ],
            \array_keys($result->toArray()),
        );

        self::assertIsArray($result->toArray()['error']);
        self::assertNotSame(
            $error,
            $result->toArray()['error'],
            'No ErrorDescriptor object may cross the hook/export boundary.',
        );

        self::assertSame(
            [
                'code',
                'extensions',
                'httpStatus',
                'message',
                'schemaVersion',
                'severity',
            ],
            \array_keys($result->toArray()['error']),
            'Exported error map must keep deterministic ErrorDescriptor key order.',
        );
    }

    public function testFromContextPreservesContextOwnedIdentityFields(): void
    {
        $context = new UnitOfWorkContext(
            uowId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            type: UnitOfWorkType::QUEUE,
            startedAt: 1_710_000_000_123,
            correlationId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
            attributes: [
                'operation' => 'consume',
            ],
        );

        $result = UnitOfWorkResult::fromContext(
            context: $context,
            finishedAt: 1_710_000_000_789,
            durationMs: 666,
            outcome: Outcome::FATAL_ERROR,
            extensions: [
                'worker' => 'primary',
            ],
        );

        self::assertSame($context->uowId(), $result->uowId());
        self::assertSame($context->type(), $result->type());
        self::assertSame($context->correlationId(), $result->correlationId());
        self::assertSame($context->startedAt(), $result->startedAt());

        self::assertSame(1_710_000_000_789, $result->finishedAt());
        self::assertSame(666, $result->durationMs());
        self::assertSame(Outcome::FATAL_ERROR, $result->outcome());
        self::assertSame(['worker' => 'primary'], $result->extensions());

        self::assertSame(
            [
                'correlationId' => '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
                'durationMs' => 666,
                'extensions' => [
                    'worker' => 'primary',
                ],
                'finishedAt' => 1_710_000_000_789,
                'outcome' => Outcome::FATAL_ERROR,
                'startedAt' => 1_710_000_000_123,
                'type' => UnitOfWorkType::QUEUE,
                'uowId' => '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            ],
            $result->toArray(),
        );
    }

    public function testFinishedAtMayBeLessThanStartedAtBecauseDurationIsCanonical(): void
    {
        $result = new UnitOfWorkResult(
            uowId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            type: UnitOfWorkType::SCHEDULER,
            correlationId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
            startedAt: 1_710_000_000_456,
            finishedAt: 1_710_000_000_123,
            durationMs: 7,
            outcome: Outcome::SUCCESS,
        );

        self::assertSame(1_710_000_000_456, $result->startedAt());
        self::assertSame(1_710_000_000_123, $result->finishedAt());
        self::assertSame(7, $result->durationMs());
    }

    public function testInvalidResultScalarFieldsFailWithResultSpecificException(): void
    {
        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult(uowId: ''),
            expectedPath: 'uowId',
            expectedReason: 'uow-result-uow-id-invalid',
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult(uowId: ' invalid '),
            expectedPath: 'uowId',
            expectedReason: 'uow-result-uow-id-invalid',
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult(type: 'http-request'),
            expectedPath: 'type',
            expectedReason: 'uow-result-type-invalid',
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult(correlationId: ''),
            expectedPath: 'correlationId',
            expectedReason: 'uow-result-correlation-id-invalid',
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult(startedAt: -1),
            expectedPath: 'startedAt',
            expectedReason: 'uow-result-started-at-invalid',
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult(finishedAt: -1),
            expectedPath: 'finishedAt',
            expectedReason: 'uow-result-finished-at-invalid',
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult(durationMs: -1),
            expectedPath: 'durationMs',
            expectedReason: 'uow-result-duration-ms-invalid',
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult(outcome: 'partial_success'),
            expectedPath: 'outcome',
            expectedReason: 'uow-result-outcome-invalid',
        );
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private static function makeResult(
        string $uowId = '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
        string $type = UnitOfWorkType::HTTP,
        string $correlationId = '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
        int $startedAt = 1_710_000_000_123,
        int $finishedAt = 1_710_000_000_456,
        int $durationMs = 333,
        string $outcome = Outcome::SUCCESS,
        ?ErrorDescriptor $error = null,
        array $extensions = [],
    ): UnitOfWorkResult {
        return new UnitOfWorkResult(
            uowId: $uowId,
            type: $type,
            correlationId: $correlationId,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            durationMs: $durationMs,
            outcome: $outcome,
            error: $error,
            extensions: $extensions,
        );
    }

    /**
     * @param callable(): mixed $operation
     */
    private static function assertResultInvalid(
        callable $operation,
        string $expectedPath,
        string $expectedReason,
    ): void {
        try {
            $operation();
            self::fail('Expected UnitOfWorkResultInvalidException was not thrown.');
        } catch (UnitOfWorkResultInvalidException $exception) {
            self::assertSame(
                UnitOfWorkResultInvalidException::ERROR_CODE,
                $exception->errorCode(),
            );

            self::assertSame(
                'CORETSIA_UOW_RESULT_INVALID',
                $exception->errorCode(),
            );

            self::assertSame($expectedPath, $exception->path());
            self::assertSame($expectedReason, $exception->reason());

            self::assertStringContainsString($expectedReason, $exception->getMessage());
            self::assertStringContainsString($expectedPath, $exception->getMessage());
        }
    }

    private static function assertParameterNamedType(
        ReflectionParameter $parameter,
        string $expectedName,
        bool $allowsNull,
    ): void {
        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());
    }
}
