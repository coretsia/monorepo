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

namespace Coretsia\Platform\Redaction\Tests\Contract;

use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;

final class RedactionProviderDefinitionsContainNoClosuresContractTest extends TestCase
{
    public function testDefinitionsHaveExactOrderTypedReferencesAndAlias(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new RedactionServiceProvider()->define($definitions, new ContainerDefinitionContext([]));
        $set = $definitions->build();
        $operations = $set->toDescriptorStream();

        self::assertSame([], $set->requiredServiceIds());
        self::assertSame(
            [
                self::classOperation(SensitiveKeyClassifier::class),
                self::classOperation(SensitiveValueClassifier::class),
                self::classOperation(StableRedactionHasher::class),
                self::classOperation(
                    DefaultSensitiveDataRedactor::class,
                    [
                        ['id' => SensitiveKeyClassifier::class, 'type' => 'service'],
                        ['id' => SensitiveValueClassifier::class, 'type' => 'service'],
                        ['id' => StableRedactionHasher::class, 'type' => 'service'],
                    ],
                ),
                [
                    'alias' => SensitiveDataRedactorInterface::class,
                    'kind' => 'alias',
                    'serviceId' => DefaultSensitiveDataRedactor::class,
                ],
            ],
            $operations,
        );

        foreach ($operations as $operation) {
            self::assertContains($operation['kind'], ['service.class', 'alias']);
            self::assertDescriptorContainsOnlyData($operation);
        }

        self::assertCount(3, $operations[3]['arguments']);
        foreach ($operations[3]['arguments'] as $argument) {
            self::assertSame('service', $argument['type']);
            self::assertSame(['id', 'type'], \array_keys($argument));
            self::assertIsString($argument['id']);
        }
    }

    /**
     * @param list<array{id: string, type: string}> $arguments
     *
     * @return array{arguments: list<array{id: string, type: string}>, class: string, id: string, kind: string, shared: true}
     */
    private static function classOperation(string $class, array $arguments = []): array
    {
        return [
            'arguments' => $arguments,
            'class' => $class,
            'id' => $class,
            'kind' => 'service.class',
            'shared' => true,
        ];
    }

    private static function assertDescriptorContainsOnlyData(mixed $value): void
    {
        self::assertFalse($value instanceof \Closure);
        self::assertFalse(\is_object($value));
        self::assertFalse(\is_resource($value));

        if (!\is_array($value)) {
            self::assertTrue($value === null || \is_scalar($value));

            return;
        }

        foreach ($value as $nested) {
            self::assertDescriptorContainsOnlyData($nested);
        }
    }
}
