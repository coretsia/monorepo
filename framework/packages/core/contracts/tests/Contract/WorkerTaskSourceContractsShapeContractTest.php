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

use Coretsia\Contracts\Worker\WorkerTaskInterface;
use Coretsia\Contracts\Worker\WorkerTaskSourceContextInterface;
use Coretsia\Contracts\Worker\WorkerTaskSourceInterface;
use Coretsia\Contracts\Worker\WorkerTaskType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

final class WorkerTaskSourceContractsShapeContractTest extends TestCase
{
    public function testWorkerTaskSourceInterfaceShapeIsStable(): void
    {
        $reflection = new ReflectionClass(WorkerTaskSourceInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertMethodNames($reflection, ['assertReady', 'receive', 'taskType']);

        $taskType = $reflection->getMethod('taskType');
        self::assertSame(0, $taskType->getNumberOfParameters());
        self::assertReturnType($taskType, WorkerTaskType::class, false);

        self::assertSingleParameterAndReturn(
            $reflection->getMethod('assertReady'),
            'context',
            WorkerTaskSourceContextInterface::class,
            false,
            'void',
            false,
        );
        self::assertSingleParameterAndReturn(
            $reflection->getMethod('receive'),
            'context',
            WorkerTaskSourceContextInterface::class,
            false,
            WorkerTaskInterface::class,
            true,
        );
    }

    public function testWorkerTaskInterfaceShapeIsStable(): void
    {
        $reflection = new ReflectionClass(WorkerTaskInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertMethodNames($reflection, ['complete', 'execute', 'fail']);

        $execute = $reflection->getMethod('execute');
        self::assertSame(0, $execute->getNumberOfParameters());
        self::assertReturnType($execute, 'mixed', true);

        self::assertSingleParameterAndReturn(
            $reflection->getMethod('complete'),
            'result',
            'mixed',
            true,
            'void',
            false,
        );
        self::assertSingleParameterAndReturn(
            $reflection->getMethod('fail'),
            'failure',
            Throwable::class,
            false,
            'void',
            false,
        );
    }

    public function testWorkerTaskSourceContextInterfaceShapeIsStable(): void
    {
        $reflection = new ReflectionClass(WorkerTaskSourceContextInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertMethodNames(
            $reflection,
            ['cancellationRequested', 'maxBlockingWaitMs', 'workerCount', 'workerIndex'],
        );

        foreach (
            [
                'workerIndex' => 'int',
                'workerCount' => 'int',
                'cancellationRequested' => 'bool',
                'maxBlockingWaitMs' => 'int',
            ] as $methodName => $returnType
        ) {
            $method = $reflection->getMethod($methodName);
            self::assertSame(0, $method->getNumberOfParameters());
            self::assertReturnType($method, $returnType, false);
        }
    }

    public function testWorkerTaskSourceContractsRemainTransportNeutral(): void
    {
        foreach (
            [
                WorkerTaskInterface::class,
                WorkerTaskSourceContextInterface::class,
                WorkerTaskSourceInterface::class,
                WorkerTaskType::class,
            ] as $class
        ) {
            $reflection = new ReflectionClass($class);
            $path = $reflection->getFileName();
            self::assertIsString($path);
            $source = \file_get_contents($path);
            self::assertIsString($source);

            foreach (
                [
                    'Coretsia\\Platform\\',
                    'Psr\\Http\\',
                    'FrankenPhp',
                    'RoadRunner',
                    'Swoole',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    /** @param list<string> $expected */
    private static function assertMethodNames(ReflectionClass $reflection, array $expected): void
    {
        $actual = \array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );
        \sort($actual, \SORT_STRING);
        \sort($expected, \SORT_STRING);

        self::assertSame($expected, $actual);
    }

    private static function assertSingleParameterAndReturn(
        ReflectionMethod $method,
        string $parameterName,
        string $parameterType,
        bool $parameterAllowsNull,
        string $returnType,
        bool $returnAllowsNull,
    ): void {
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertParameterType(
            $method->getParameters()[0],
            $parameterName,
            $parameterType,
            $parameterAllowsNull,
        );
        self::assertReturnType($method, $returnType, $returnAllowsNull);
    }

    private static function assertParameterType(
        ReflectionParameter $parameter,
        string $expectedName,
        string $expectedType,
        bool $allowsNull,
    ): void {
        self::assertSame($expectedName, $parameter->getName());
        $type = $parameter->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedType, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());
    }

    private static function assertReturnType(
        ReflectionMethod $method,
        string $expected,
        bool $allowsNull,
    ): void {
        $type = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expected, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());
    }
}
