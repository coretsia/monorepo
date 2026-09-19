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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Config\MergeStrategyInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MergeStrategyInterfaceShapeContractTest extends TestCase
{
    public function testMergeStrategyInterfaceExposesMergeOnly(): void
    {
        $interface = new ReflectionClass(MergeStrategyInterface::class);

        self::assertTrue($interface->isInterface());

        self::assertSame(
            ['merge'],
            array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $interface->getMethods(),
            ),
        );
    }

    public function testMergeMethodShapeIsBinaryNodeMergeBoundary(): void
    {
        $method = new \ReflectionMethod(MergeStrategyInterface::class, 'merge');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(2, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('base', $parameters[0]->getName());
        self::assertSame('patch', $parameters[1]->getName());

        self::assertSame('mixed', (string)$parameters[0]->getType());
        self::assertSame('mixed', (string)$parameters[1]->getType());
        self::assertSame('mixed', (string)$method->getReturnType());
    }

    public function testMergeMethodDocblockCementsSideEffectFreeBinaryPolicy(): void
    {
        $method = new \ReflectionMethod(MergeStrategyInterface::class, 'merge');

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('Merges two config nodes deterministically.', $docComment);
        self::assertStringContainsString('side-effect free', $docComment);
        self::assertStringContainsString('folding this operation', $docComment);
    }
}
