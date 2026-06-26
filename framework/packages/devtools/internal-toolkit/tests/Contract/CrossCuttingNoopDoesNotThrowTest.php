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

namespace Coretsia\Devtools\InternalToolkit\Tests\Contract;

use Coretsia\Devtools\InternalToolkit\Json;
use Coretsia\Devtools\InternalToolkit\Path;
use Coretsia\Devtools\InternalToolkit\Slug;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testOwnedApiClassesRemainFinalStaticOnlyHelpers(): void
    {
        foreach ([Json::class, Path::class, Slug::class] as $className) {
            $class = new ReflectionClass($className);

            self::assertTrue($class->isFinal(), $className . ' must remain final.');

            $constructor = $class->getConstructor();

            self::assertNotNull($constructor, $className . ' must declare a constructor.');
            self::assertTrue($constructor->isPrivate(), $className . ' must not be instantiable.');
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function testOwnedApiMethodsRemainPublicStaticStringReturningHelpers(): void
    {
        $this->assertPublicStaticStringMethod(Json::class, 'encodeStable');
        $this->assertPublicStaticStringMethod(Path::class, 'normalizeRelative');
        $this->assertPublicStaticStringMethod(Slug::class, 'toStudly');
        $this->assertPublicStaticStringMethod(Slug::class, 'toSnake');
    }

    /**
     * @throws \JsonException
     */
    public function testOwnedHelpersComposeIntoStableToolingPayload(): void
    {
        $payload = [
            'slug' => Slug::toSnake('InternalToolkit'),
            'class' => Slug::toStudly('internal-toolkit'),
            'path' => Path::normalizeRelative(
                '/repo/framework/packages/devtools/internal-toolkit',
                '/repo',
            ),
            'meta' => [
                'z' => true,
                'a' => null,
            ],
        ];

        self::assertSame(
            '{"class":"InternalToolkit","meta":{"a":null,"z":true},"path":"framework/packages/devtools/internal-toolkit","slug":"internal_toolkit"}',
            Json::encodeStable($payload),
        );
    }

    /**
     * @throws \ReflectionException
     */
    private function assertPublicStaticStringMethod(string $className, string $methodName): void
    {
        self::assertTrue(method_exists($className, $methodName), $className . '::' . $methodName . ' must exist.');

        $method = new ReflectionMethod($className, $methodName);

        self::assertTrue($method->isPublic(), $className . '::' . $methodName . ' must be public.');
        self::assertTrue($method->isStatic(), $className . '::' . $methodName . ' must be static.');

        $returnType = $method->getReturnType();

        self::assertInstanceOf(
            ReflectionNamedType::class,
            $returnType,
            $className . '::' . $methodName . ' must declare a named return type.',
        );

        self::assertSame('string', $returnType->getName());
    }
}
