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

use Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException;
use Coretsia\Kernel\Runtime\UnitOfWorkContext;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;

final class UnitOfWorkContextShapeContractTest extends TestCase
{
    public function testContextTypeVocabularyIsStable(): void
    {
        self::assertSame(
            [
                UnitOfWorkType::HTTP,
                UnitOfWorkType::CLI,
                UnitOfWorkType::QUEUE,
                UnitOfWorkType::SCHEDULER,
            ],
            UnitOfWorkType::values(),
            'UnitOfWork type tokens must remain stable in declaration order.',
        );

        self::assertSame('http', UnitOfWorkType::HTTP);
        self::assertSame('cli', UnitOfWorkType::CLI);
        self::assertSame('queue', UnitOfWorkType::QUEUE);
        self::assertSame('scheduler', UnitOfWorkType::SCHEDULER);
    }

    public function testContextTypeValidationIsByteForByteAndDoesNotNormalizeInput(): void
    {
        self::assertTrue(UnitOfWorkType::isValid('http'));
        self::assertTrue(UnitOfWorkType::isValid('cli'));
        self::assertTrue(UnitOfWorkType::isValid('queue'));
        self::assertTrue(UnitOfWorkType::isValid('scheduler'));

        self::assertFalse(UnitOfWorkType::isValid('HTTP'));
        self::assertFalse(UnitOfWorkType::isValid(' http '));
        self::assertFalse(UnitOfWorkType::isValid('web'));
        self::assertFalse(UnitOfWorkType::isValid(''));
    }

    public function testContextExportsCanonicalShapeWithStableTopLevelKeyOrder(): void
    {
        $context = new UnitOfWorkContext(
            uowId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            type: UnitOfWorkType::HTTP,
            startedAt: 1_710_000_000_123,
            correlationId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
            attributes: [
                'zeta' => 2,
                'alpha' => [
                    'delta' => 4,
                    'beta' => true,
                ],
                'list' => [
                    [
                        'b' => 2,
                        'a' => 1,
                    ],
                ],
            ],
        );

        $shape = $context->toArray();

        self::assertSame(
            [
                'attributes',
                'correlationId',
                'startedAt',
                'type',
                'uowId',
            ],
            \array_keys($shape),
            'UnitOfWorkContext exported top-level key order must stay canonical.',
        );

        self::assertSame(
            [
                'alpha' => [
                    'beta' => true,
                    'delta' => 4,
                ],
                'list' => [
                    [
                        'a' => 1,
                        'b' => 2,
                    ],
                ],
                'zeta' => 2,
            ],
            $shape['attributes'],
            'UnitOfWorkContext attributes must be normalized before export.',
        );

        self::assertSame('01HV7N3ZJ5P8K7Y6T4R3Q2P1N1', $shape['correlationId']);
        self::assertSame(1_710_000_000_123, $shape['startedAt']);
        self::assertSame(UnitOfWorkType::HTTP, $shape['type']);
        self::assertSame('01HV7N3ZJ5P8K7Y6T4R3Q2P1N0', $shape['uowId']);
    }

    public function testContextAccessorsExposeCanonicalFields(): void
    {
        $context = new UnitOfWorkContext(
            uowId: 'uow-001',
            type: UnitOfWorkType::CLI,
            startedAt: 1_710_000_000_456,
            correlationId: 'corr-001',
            attributes: [
                'operation' => 'command',
                'attempt' => 1,
            ],
        );

        self::assertSame('uow-001', $context->uowId());
        self::assertSame(UnitOfWorkType::CLI, $context->type());
        self::assertSame(1_710_000_000_456, $context->startedAt());
        self::assertSame('corr-001', $context->correlationId());
        self::assertSame(
            [
                'attempt' => 1,
                'operation' => 'command',
            ],
            $context->attributes(),
        );
    }

    public function testContextAcceptsEveryCanonicalUnitOfWorkType(): void
    {
        foreach (UnitOfWorkType::values() as $type) {
            $context = new UnitOfWorkContext(
                uowId: 'uow-' . $type,
                type: $type,
                startedAt: 1_710_000_000_789,
                correlationId: 'corr-' . $type,
            );

            self::assertSame($type, $context->type());
            self::assertSame($type, $context->toArray()['type']);
        }
    }

    public function testContextRejectsInvalidUnitOfWorkTypeWithStableDiagnostics(): void
    {
        self::assertContextInvalid(
            static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                uowId: 'uow-001',
                type: 'web',
                startedAt: 1_710_000_000_000,
                correlationId: 'corr-001',
            ),
            expectedPath: 'type',
            expectedReason: 'uow-context-type-invalid',
        );
    }

    public function testContextRejectsInvalidScalarFieldsWithStableDiagnostics(): void
    {
        $cases = [
            'empty uowId' => [
                static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                    uowId: '',
                    type: UnitOfWorkType::HTTP,
                    startedAt: 1_710_000_000_000,
                    correlationId: 'corr-001',
                ),
                'uowId',
                'uow-context-uow-id-invalid',
            ],
            'uowId with surrounding whitespace' => [
                static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                    uowId: ' uow-001',
                    type: UnitOfWorkType::HTTP,
                    startedAt: 1_710_000_000_000,
                    correlationId: 'corr-001',
                ),
                'uowId',
                'uow-context-uow-id-invalid',
            ],
            'uowId with newline' => [
                static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                    uowId: "uow-001\n",
                    type: UnitOfWorkType::HTTP,
                    startedAt: 1_710_000_000_000,
                    correlationId: 'corr-001',
                ),
                'uowId',
                'uow-context-uow-id-invalid',
            ],
            'negative startedAt' => [
                static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                    uowId: 'uow-001',
                    type: UnitOfWorkType::HTTP,
                    startedAt: -1,
                    correlationId: 'corr-001',
                ),
                'startedAt',
                'uow-context-started-at-invalid',
            ],
            'empty correlationId' => [
                static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                    uowId: 'uow-001',
                    type: UnitOfWorkType::HTTP,
                    startedAt: 1_710_000_000_000,
                    correlationId: '',
                ),
                'correlationId',
                'uow-context-correlation-id-invalid',
            ],
            'correlationId with surrounding whitespace' => [
                static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                    uowId: 'uow-001',
                    type: UnitOfWorkType::HTTP,
                    startedAt: 1_710_000_000_000,
                    correlationId: ' corr-001',
                ),
                'correlationId',
                'uow-context-correlation-id-invalid',
            ],
            'correlationId with newline' => [
                static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                    uowId: 'uow-001',
                    type: UnitOfWorkType::HTTP,
                    startedAt: 1_710_000_000_000,
                    correlationId: "corr-001\n",
                ),
                'correlationId',
                'uow-context-correlation-id-invalid',
            ],
        ];

        foreach ($cases as $caseName => [$operation, $expectedPath, $expectedReason]) {
            self::assertContextInvalid(
                operation: $operation,
                expectedPath: $expectedPath,
                expectedReason: $expectedReason,
                caseName: $caseName,
            );
        }
    }

    public function testContextRejectsInvalidAttributeLimitsWithStableDiagnostics(): void
    {
        $cases = [
            'zero max depth' => [
                static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                    uowId: 'uow-001',
                    type: UnitOfWorkType::HTTP,
                    startedAt: 1_710_000_000_000,
                    correlationId: 'corr-001',
                    attributes: [],
                    attributesMaxDepth: 0,
                    attributesMaxKeys: 200,
                ),
                'attributes',
                'uow-context-attributes-max-depth-invalid',
            ],
            'zero max keys' => [
                static fn (): UnitOfWorkContext => new UnitOfWorkContext(
                    uowId: 'uow-001',
                    type: UnitOfWorkType::HTTP,
                    startedAt: 1_710_000_000_000,
                    correlationId: 'corr-001',
                    attributes: [],
                    attributesMaxDepth: 10,
                    attributesMaxKeys: 0,
                ),
                'attributes',
                'uow-context-attributes-max-keys-invalid',
            ],
        ];

        foreach ($cases as $caseName => [$operation, $expectedPath, $expectedReason]) {
            self::assertContextInvalid(
                operation: $operation,
                expectedPath: $expectedPath,
                expectedReason: $expectedReason,
                caseName: $caseName,
            );
        }
    }

    /**
     * @param callable(): mixed $operation
     */
    private static function assertContextInvalid(
        callable $operation,
        string $expectedPath,
        string $expectedReason,
        string $caseName = '',
    ): void {
        try {
            $operation();
            self::fail(
                ($caseName === '' ? '' : $caseName . ': ')
                . 'Expected UnitOfWorkContextInvalidException was not thrown.',
            );
        } catch (UnitOfWorkContextInvalidException $exception) {
            self::assertSame(
                UnitOfWorkContextInvalidException::ERROR_CODE,
                $exception->errorCode(),
                $caseName,
            );

            self::assertSame(
                'CORETSIA_UOW_CONTEXT_INVALID',
                $exception->errorCode(),
                $caseName,
            );

            self::assertSame($expectedPath, $exception->path(), $caseName);
            self::assertSame($expectedReason, $exception->reason(), $caseName);

            self::assertStringNotContainsString('secret-value', $exception->getMessage(), $caseName);
            self::assertStringNotContainsString('token-value', $exception->getMessage(), $caseName);
            self::assertStringNotContainsString('password-value', $exception->getMessage(), $caseName);
        }
    }
}
