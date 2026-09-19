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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use PHPUnit\Framework\TestCase;

final class ContainerDefinitionSetRejectsRuntimeValuesContractTest extends TestCase
{
    public function testBuilderRejectsClosureObjectFloatAndResourceParameterValues(): void
    {
        self::assertDefinitionInvalid(
            operation: static fn (): ContainerDefinitionSet => new ContainerDefinitionBuilder()
                ->parameter(
                    'runtime.value',
                    static fn (): string => 'raw-closure-secret',
                )
                ->build(),
            expectedReason: ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            forbiddenDiagnosticsNeedles: [
                'raw-closure-secret',
                'Closure',
            ],
        );

        $object = new class() {
            public string $secret = 'raw-object-secret';
        };

        self::assertDefinitionInvalid(
            operation: static fn (): ContainerDefinitionSet => new ContainerDefinitionBuilder()
                ->parameter('runtime.value', $object)
                ->build(),
            expectedReason: ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            forbiddenDiagnosticsNeedles: [
                'raw-object-secret',
                $object::class,
                'class@anonymous',
            ],
        );

        self::assertDefinitionInvalid(
            operation: static fn (): ContainerDefinitionSet => new ContainerDefinitionBuilder()
                ->parameter('runtime.value', 12.5)
                ->build(),
            expectedReason: ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            forbiddenDiagnosticsNeedles: [
                '12.5',
            ],
        );

        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            self::assertDefinitionInvalid(
                operation: static fn (): ContainerDefinitionSet => new ContainerDefinitionBuilder()
                    ->parameter('runtime.value', $resource)
                    ->build(),
                expectedReason: ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
                forbiddenDiagnosticsNeedles: [
                    'php://memory',
                    'resource',
                ],
            );
        } finally {
            \fclose($resource);
        }
    }

    public function testBuilderRejectsRuntimeValuesNestedInsideServiceArguments(): void
    {
        self::assertDefinitionInvalid(
            operation: static fn (): ContainerDefinitionSet => new ContainerDefinitionBuilder()
                ->classService(
                    id: 'definition.runtime_object',
                    class: ContainerDefinitionSetRejectsRuntimeValuesSubject::class,
                    arguments: [
                        [
                            'nested' => new \stdClass(),
                        ],
                    ],
                )
                ->build(),
            expectedReason: ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            forbiddenDiagnosticsNeedles: [
                'stdClass',
            ],
        );

        self::assertDefinitionInvalid(
            operation: static fn (): ContainerDefinitionSet => new ContainerDefinitionBuilder()
                ->classService(
                    id: 'definition.runtime_closure',
                    class: ContainerDefinitionSetRejectsRuntimeValuesSubject::class,
                    arguments: [
                        [
                            'nested' => static fn (): string => 'nested-closure-secret',
                        ],
                    ],
                )
                ->build(),
            expectedReason: ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            forbiddenDiagnosticsNeedles: [
                'nested-closure-secret',
                'Closure',
            ],
        );
    }

    public function testBuilderRejectsRawReferenceMaps(): void
    {
        self::assertDefinitionInvalid(
            operation: static fn (): ContainerDefinitionSet => new ContainerDefinitionBuilder()
                ->classService(
                    id: 'definition.raw_reference',
                    class: ContainerDefinitionSetRejectsRuntimeValuesSubject::class,
                    arguments: [
                        [
                            'id' => 'dependency.service',
                            'type' => 'service',
                        ],
                    ],
                )
                ->build(),
            expectedReason: ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
            forbiddenDiagnosticsNeedles: [
                'dependency.service',
            ],
        );
    }

    public function testValidatedStateRejectsTypedReferenceObjectsAndRuntimeObjects(): void
    {
        self::assertDefinitionInvalid(
            operation: static fn (): ContainerDefinitionSet =>
                ContainerDefinitionSet::fromValidatedState(
                    operations: [
                        [
                            'arguments' => [
                                ContainerValueReference::service('dependency.service'),
                            ],
                            'class' => ContainerDefinitionSetRejectsRuntimeValuesSubject::class,
                            'id' => 'definition.reference_object',
                            'kind' => 'service.class',
                            'shared' => true,
                        ],
                    ],
                    requiredServiceIds: [],
                ),
            expectedReason: ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
            forbiddenDiagnosticsNeedles: [
                'dependency.service',
                ContainerValueReference::class,
            ],
        );

        self::assertDefinitionInvalid(
            operation: static fn (): ContainerDefinitionSet =>
                ContainerDefinitionSet::fromValidatedState(
                    operations: [
                        [
                            'arguments' => [
                                new \stdClass(),
                            ],
                            'class' => ContainerDefinitionSetRejectsRuntimeValuesSubject::class,
                            'id' => 'definition.runtime_object',
                            'kind' => 'service.class',
                            'shared' => true,
                        ],
                    ],
                    requiredServiceIds: [],
                ),
            expectedReason: ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            forbiddenDiagnosticsNeedles: [
                'stdClass',
            ],
        );
    }

    /**
     * @param callable(): mixed $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertDefinitionInvalid(
        callable $operation,
        string $expectedReason,
        array $forbiddenDiagnosticsNeedles,
    ): void {
        try {
            $operation();
        } catch (ContainerDefinitionInvalidException $exception) {
            self::assertSame(
                ContainerDefinitionInvalidException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ContainerDefinitionInvalidException::MESSAGE_TOKEN,
                $exception->messageToken(),
            );
            self::assertSame($expectedReason, $exception->reason());
            self::assertSame(
                ContainerDefinitionInvalidException::ERROR_CODE
                . ': '
                . ContainerDefinitionInvalidException::MESSAGE_TOKEN,
                $exception->getMessage(),
            );

            foreach ($forbiddenDiagnosticsNeedles as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $exception->getMessage(),
                );
            }

            return;
        }

        self::fail(
            'Expected declarative container definition state to be rejected with reason: '
            . $expectedReason,
        );
    }
}

final class ContainerDefinitionSetRejectsRuntimeValuesSubject
{
}
