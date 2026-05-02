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

use Coretsia\Contracts\Routing\RouteMatch;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use stdClass;

final class RouteMatchShapeContractTest extends TestCase
{
    public function test_constructor_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(RouteMatch::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(5, $parameters);
        self::assertSame(3, $constructor->getNumberOfRequiredParameters());

        self::assertSame('name', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], 'string', false);

        self::assertSame('pathTemplate', $parameters[1]->getName());
        self::assertParameterNamedType($parameters[1], 'string', false);

        self::assertSame('handler', $parameters[2]->getName());
        self::assertParameterNamedType($parameters[2], 'string', false);

        self::assertSame('parameters', $parameters[3]->getName());
        self::assertParameterNamedType($parameters[3], 'array', false);
        self::assertTrue($parameters[3]->isDefaultValueAvailable());
        self::assertSame([], $parameters[3]->getDefaultValue());

        self::assertSame('metadata', $parameters[4]->getName());
        self::assertParameterNamedType($parameters[4], 'array', false);
        self::assertTrue($parameters[4]->isDefaultValueAvailable());
        self::assertSame([], $parameters[4]->getDefaultValue());
    }

    public function test_accessors_and_array_shape_are_stable(): void
    {
        $match = new RouteMatch(
            name: 'users.show',
            pathTemplate: '/users/{id}',
            handler: 'UsersController::show',
            parameters: [
                'slug' => 'john',
                'id' => '123',
            ],
            metadata: [
                'z' => [
                    'b' => false,
                    'a' => true,
                ],
                'operation' => 'show',
            ],
        );

        self::assertSame(1, $match->schemaVersion());
        self::assertSame('users.show', $match->name());
        self::assertSame('/users/{id}', $match->pathTemplate());
        self::assertSame('UsersController::show', $match->handler());
        self::assertSame(
            [
                'id' => '123',
                'slug' => 'john',
            ],
            $match->parameters(),
        );
        self::assertSame(
            [
                'operation' => 'show',
                'z' => [
                    'a' => true,
                    'b' => false,
                ],
            ],
            $match->metadata(),
        );

        self::assertSame(
            [
                'handler' => 'UsersController::show',
                'metadata' => [
                    'operation' => 'show',
                    'z' => [
                        'a' => true,
                        'b' => false,
                    ],
                ],
                'name' => 'users.show',
                'parameters' => [
                    'id' => '123',
                    'slug' => 'john',
                ],
                'pathTemplate' => '/users/{id}',
                'schemaVersion' => 1,
            ],
            $match->toArray(),
        );

        self::assertSame(
            [
                'handler',
                'metadata',
                'name',
                'parameters',
                'pathTemplate',
                'schemaVersion',
            ],
            array_keys($match->toArray()),
        );
    }

    public function test_path_template_must_start_with_slash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RouteMatch(
            name: 'users.show',
            pathTemplate: 'users/{id}',
            handler: 'UsersController::show',
        );
    }

    public function test_parameters_reject_root_lists(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RouteMatch(
            name: 'users.show',
            pathTemplate: '/users/{id}',
            handler: 'UsersController::show',
            parameters: ['123'],
        );
    }

    public function test_parameters_reject_invalid_keys_and_values(): void
    {
        $invalidCases = [
            'empty-key' => [
                '' => '123',
            ],
            'whitespace-key' => [
                'bad key' => '123',
            ],
            'empty-value' => [
                'id' => '',
            ],
            'non-string-value' => [
                'id' => 123,
            ],
        ];

        foreach ($invalidCases as $label => $parameters) {
            try {
                new RouteMatch(
                    name: 'users.show',
                    pathTemplate: '/users/{id}',
                    handler: 'UsersController::show',
                    parameters: $parameters,
                );

                self::fail(sprintf('Expected route match invalid parameters case "%s" to throw.', $label));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_metadata_rejects_invalid_json_like_values(): void
    {
        $invalidCases = [
            'root-list' => ['root-list-value'],
            'float' => [
                'value' => 1.5,
            ],
            'nan' => [
                'value' => \NAN,
            ],
            'inf' => [
                'value' => \INF,
            ],
            'object' => [
                'value' => new stdClass(),
            ],
            'closure' => [
                'value' => static fn (): string => 'invalid',
            ],
            'empty-key' => [
                '' => 'value',
            ],
        ];

        foreach ($invalidCases as $label => $metadata) {
            try {
                new RouteMatch(
                    name: 'users.show',
                    pathTemplate: '/users/{id}',
                    handler: 'UsersController::show',
                    metadata: $metadata,
                );

                self::fail(sprintf('Expected route match invalid metadata case "%s" to throw.', $label));
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
