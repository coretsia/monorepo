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
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use stdClass;

final class RouteDefinitionShapeContractTest extends TestCase
{
    public function test_constructor_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(RouteDefinition::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(7, $parameters);
        self::assertSame(4, $constructor->getNumberOfRequiredParameters());

        self::assertSame('name', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], 'string', false);

        self::assertSame('methods', $parameters[1]->getName());
        self::assertParameterNamedType($parameters[1], 'array', false);

        self::assertSame('pathTemplate', $parameters[2]->getName());
        self::assertParameterNamedType($parameters[2], 'string', false);

        self::assertSame('handler', $parameters[3]->getName());
        self::assertParameterNamedType($parameters[3], 'string', false);

        self::assertSame('requirements', $parameters[4]->getName());
        self::assertParameterNamedType($parameters[4], 'array', false);
        self::assertTrue($parameters[4]->isDefaultValueAvailable());
        self::assertSame([], $parameters[4]->getDefaultValue());

        self::assertSame('defaults', $parameters[5]->getName());
        self::assertParameterNamedType($parameters[5], 'array', false);
        self::assertTrue($parameters[5]->isDefaultValueAvailable());
        self::assertSame([], $parameters[5]->getDefaultValue());

        self::assertSame('metadata', $parameters[6]->getName());
        self::assertParameterNamedType($parameters[6], 'array', false);
        self::assertTrue($parameters[6]->isDefaultValueAvailable());
        self::assertSame([], $parameters[6]->getDefaultValue());
    }

    public function test_accessors_and_array_shape_are_stable(): void
    {
        $route = new RouteDefinition(
            name: 'users.show',
            methods: ['post', 'GET', 'get'],
            pathTemplate: '/users/{id}',
            handler: 'UsersController::show',
            requirements: [
                'slug' => '[a-z]+',
                'id' => '\d+',
            ],
            defaults: [
                'z' => [
                    'b' => false,
                    'a' => true,
                ],
                'page' => 1,
            ],
            metadata: [
                'z' => 'last',
                'a' => 'first',
            ],
        );

        self::assertSame(1, $route->schemaVersion());
        self::assertSame('users.show', $route->name());
        self::assertSame(['GET', 'POST'], $route->methods());
        self::assertSame('/users/{id}', $route->pathTemplate());
        self::assertSame('UsersController::show', $route->handler());
        self::assertSame(
            [
                'id' => '\d+',
                'slug' => '[a-z]+',
            ],
            $route->requirements(),
        );
        self::assertSame(
            [
                'page' => 1,
                'z' => [
                    'a' => true,
                    'b' => false,
                ],
            ],
            $route->defaults(),
        );
        self::assertSame(
            [
                'a' => 'first',
                'z' => 'last',
            ],
            $route->metadata(),
        );

        self::assertSame(
            [
                'defaults' => [
                    'page' => 1,
                    'z' => [
                        'a' => true,
                        'b' => false,
                    ],
                ],
                'handler' => 'UsersController::show',
                'metadata' => [
                    'a' => 'first',
                    'z' => 'last',
                ],
                'methods' => [
                    'GET',
                    'POST',
                ],
                'name' => 'users.show',
                'pathTemplate' => '/users/{id}',
                'requirements' => [
                    'id' => '\d+',
                    'slug' => '[a-z]+',
                ],
                'schemaVersion' => 1,
            ],
            $route->toArray(),
        );

        self::assertSame(
            [
                'defaults',
                'handler',
                'metadata',
                'methods',
                'name',
                'pathTemplate',
                'requirements',
                'schemaVersion',
            ],
            array_keys($route->toArray()),
        );
    }

    public function test_methods_are_normalized_uppercase_unique_and_sorted(): void
    {
        $route = new RouteDefinition(
            name: 'users.index',
            methods: ['post', 'GET', 'get', 'PATCH'],
            pathTemplate: '/users',
            handler: 'UsersController::index',
        );

        self::assertSame(['GET', 'PATCH', 'POST'], $route->methods());
    }

    public function test_path_template_must_start_with_slash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RouteDefinition(
            name: 'users.index',
            methods: ['GET'],
            pathTemplate: 'users',
            handler: 'UsersController::index',
        );
    }

    public function test_identity_fields_reject_whitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RouteDefinition(
            name: 'users index',
            methods: ['GET'],
            pathTemplate: '/users',
            handler: 'UsersController::index',
        );
    }

    public function test_json_like_maps_reject_invalid_values(): void
    {
        $invalidCases = [
            'defaults-root-list' => [
                'defaults' => ['root-list-value'],
                'metadata' => [],
            ],
            'metadata-root-list' => [
                'defaults' => [],
                'metadata' => ['root-list-value'],
            ],
            'float' => [
                'defaults' => [
                    'value' => 1.5,
                ],
                'metadata' => [],
            ],
            'nan' => [
                'defaults' => [
                    'value' => \NAN,
                ],
                'metadata' => [],
            ],
            'inf' => [
                'defaults' => [
                    'value' => \INF,
                ],
                'metadata' => [],
            ],
            'object' => [
                'defaults' => [
                    'value' => new stdClass(),
                ],
                'metadata' => [],
            ],
            'closure' => [
                'defaults' => [
                    'value' => static fn (): string => 'invalid',
                ],
                'metadata' => [],
            ],
            'empty-key' => [
                'defaults' => [
                    '' => 'value',
                ],
                'metadata' => [],
            ],
        ];

        foreach ($invalidCases as $label => $case) {
            try {
                new RouteDefinition(
                    name: 'users.index',
                    methods: ['GET'],
                    pathTemplate: '/users',
                    handler: 'UsersController::index',
                    defaults: $case['defaults'],
                    metadata: $case['metadata'],
                );

                self::fail(sprintf('Expected route definition invalid case "%s" to throw.', $label));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    private static function assertParameterNamedType(
        ReflectionParameter $parameter,
        string $expectedName,
        bool $expectedAllowsNull,
    ): void {
        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }
}
