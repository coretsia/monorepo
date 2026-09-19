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

use Coretsia\Contracts\Module\ModePresetInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class ModePresetInterfaceShapeContractTest extends TestCase
{
    public function testModePresetConstantsAreStable(): void
    {
        self::assertSame(1, ModePresetInterface::SCHEMA_VERSION);
        self::assertSame('micro', ModePresetInterface::MICRO);
        self::assertSame('express', ModePresetInterface::EXPRESS);
        self::assertSame('hybrid', ModePresetInterface::HYBRID);
        self::assertSame('enterprise', ModePresetInterface::ENTERPRISE);
    }

    public function testModePresetInterfaceShapeIsStable(): void
    {
        $interface = new ReflectionClass(ModePresetInterface::class);

        self::assertTrue($interface->isInterface());

        self::assertSame(
            [
                'schemaVersion',
                'name',
                'description',
                'required',
                'optional',
                'disabled',
                'moduleIds',
                'featureBundles',
                'metadata',
                'toArray',
            ],
            array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $interface->getMethods(),
            ),
        );
    }

    public function testModePresetAccessorReturnTypesAreStable(): void
    {
        self::assertMethodReturnType('schemaVersion', 'int', false);
        self::assertMethodReturnType('name', 'string', false);
        self::assertMethodReturnType('description', 'string', true);
        self::assertMethodReturnType('required', 'array', false);
        self::assertMethodReturnType('optional', 'array', false);
        self::assertMethodReturnType('disabled', 'array', false);
        self::assertMethodReturnType('moduleIds', 'array', false);
        self::assertMethodReturnType('featureBundles', 'array', false);
        self::assertMethodReturnType('metadata', 'array', false);
        self::assertMethodReturnType('toArray', 'array', false);
    }

    public function testModePresetDocblocksCementModuleIdListsAndExportedShape(): void
    {
        self::assertMethodDocContains('name', '@return non-empty-string');
        self::assertMethodDocContains('required', '@return list<ModuleId>');
        self::assertMethodDocContains('optional', '@return list<ModuleId>');
        self::assertMethodDocContains('disabled', '@return list<ModuleId>');
        self::assertMethodDocContains('moduleIds', '@return list<ModuleId>');
        self::assertMethodDocContains('featureBundles', '@return array<string,mixed>');
        self::assertMethodDocContains('metadata', '@return array<string,mixed>');
        self::assertMethodDocContains('toArray', '@return array<string,mixed>');
    }

    private static function assertMethodReturnType(string $methodName, string $expectedName, bool $allowsNull): void
    {
        $method = new \ReflectionMethod(ModePresetInterface::class, $methodName);

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(0, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame($expectedName, $returnType->getName());
        self::assertSame($allowsNull, $returnType->allowsNull());
    }

    private static function assertMethodDocContains(string $methodName, string $expected): void
    {
        $docComment = new \ReflectionMethod(ModePresetInterface::class, $methodName)->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString($expected, $docComment);
    }
}
