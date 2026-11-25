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

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteProviderInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class RouteProviderInterfaceShapeContractTest extends TestCase
{
    public function testRouteProviderInterfaceExposesStableProviderIdAndRoutesOnly(): void
    {
        $interface = new ReflectionClass(RouteProviderInterface::class);

        self::assertTrue($interface->isInterface());
        self::assertSame([], $interface->getInterfaceNames());

        $methods = $interface->getMethods();

        self::assertSame(
            ['id', 'routes'],
            array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $methods),
        );
    }

    public function testRouteProviderIdMethodShapeIsStable(): void
    {
        $method = new \ReflectionMethod(RouteProviderInterface::class, 'id');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(0, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('string', $returnType->getName());
        self::assertFalse($returnType->allowsNull());

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@return non-empty-string', $docComment);
        self::assertStringContainsString(
            'The provider id MUST be stable, non-empty, safe, and single-line.',
            $docComment,
        );
    }

    public function testRouteProviderRoutesMethodShapeIsStable(): void
    {
        $method = new \ReflectionMethod(RouteProviderInterface::class, 'routes');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(0, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('array', $returnType->getName());
        self::assertFalse($returnType->allowsNull());

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@return list<RouteDefinition>', $docComment);
        self::assertStringContainsString('Provider outputs MUST NOT contain duplicate route names.', $docComment);
        self::assertStringContainsString('The returned list MUST contain only RouteDefinition instances,', $docComment);
        self::assertStringContainsString('The returned list MUST preserve deterministic provider-emitted', $docComment);
        self::assertStringContainsString(
            'route order. This order defines the declared matching precedence',
            $docComment,
        );
        self::assertStringContainsString('within the provider and MUST NOT be reordered by route name.', $docComment);
    }

    public function testRouteProviderCanReturnDeterministicRouteDefinitionList(): void
    {
        $provider = new class() implements RouteProviderInterface {
            public function id(): string
            {
                return 'test.app';
            }

            public function routes(): array
            {
                return [
                    new RouteDefinition(
                        name: 'beta.index',
                        methods: ['GET'],
                        pathTemplate: '/beta',
                        handler: 'BetaController::index',
                    ),
                    new RouteDefinition(
                        name: 'alpha.index',
                        methods: ['GET'],
                        pathTemplate: '/alpha',
                        handler: 'AlphaController::index',
                    ),
                ];
            }
        };

        self::assertSame('test.app', $provider->id());

        $routes = $provider->routes();

        self::assertTrue(array_is_list($routes));
        self::assertContainsOnlyInstancesOf(RouteDefinition::class, $routes);
        self::assertSame(
            ['beta.index', 'alpha.index'],
            array_map(
                static fn (RouteDefinition $route): string => $route->name(),
                $routes,
            ),
        );
        self::assertSame(['beta.index', 'alpha.index'], self::uniqueRouteNames($routes));

        $repeated = $provider->routes();

        self::assertTrue(array_is_list($repeated));
        self::assertContainsOnlyInstancesOf(RouteDefinition::class, $repeated);
        self::assertSame(
            array_map(static fn (RouteDefinition $route): array => $route->toArray(), $routes),
            array_map(static fn (RouteDefinition $route): array => $route->toArray(), $repeated),
        );
    }

    /**
     * @param list<RouteDefinition> $routes
     *
     * @return list<string>
     */
    private static function uniqueRouteNames(array $routes): array
    {
        $names = array_map(
            static fn (RouteDefinition $route): string => $route->name(),
            $routes,
        );

        return array_values(array_unique($names));
    }
}
