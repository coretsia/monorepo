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

namespace Coretsia\Foundation\Tests\Unit;

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\Exception\ContainerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContainerCanAutowireIsStrictOnMissingConfigTest extends TestCase
{
    public function testCanAutowireFailsDeterministicallyWhenFoundationConfigIsMissing(): void
    {
        self::assertCanAutowireFailsWith(
            config: [],
            expectedMessage: 'container-config-foundation-missing',
        );
    }

    public function testCanAutowireFailsDeterministicallyWhenFoundationConfigIsNotAMap(): void
    {
        self::assertCanAutowireFailsWith(
            config: [
                'foundation' => true,
            ],
            expectedMessage: 'container-config-foundation-missing',
        );
    }

    public function testCanAutowireFailsDeterministicallyWhenFoundationContainerConfigIsMissing(): void
    {
        self::assertCanAutowireFailsWith(
            config: [
                'foundation' => [],
            ],
            expectedMessage: 'container-config-foundation-container-missing',
        );
    }

    public function testCanAutowireFailsDeterministicallyWhenFoundationContainerConfigIsNotAMap(): void
    {
        self::assertCanAutowireFailsWith(
            config: [
                'foundation' => [
                    'container' => true,
                ],
            ],
            expectedMessage: 'container-config-foundation-container-missing',
        );
    }

    public function testCanAutowireFailsDeterministicallyWhenFoundationContainerConfigShapeIsInvalid(): void
    {
        self::assertCanAutowireFailsWith(
            config: [
                'foundation' => [
                    'container' => [],
                ],
            ],
            expectedMessage: 'container-config-foundation-container-invalid',
        );
    }

    #[DataProvider('invalidAutowirePolicyConfigProvider')]
    public function testHasFailsDeterministicallyForConcreteClassesWhenStrictAutowirePolicyCannotBeEvaluated(
        array $config,
        string $expectedMessage,
    ): void {
        $container = new Container(config: $config);

        try {
            $container->has(ContainerStrictConfigConcreteFixture::class);
        } catch (ContainerException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
            self::assertSame(ContainerException::ERROR_CODE, $exception->errorCode());

            return;
        }

        self::fail('Expected has() to fail deterministically when strict autowire policy cannot be evaluated.');
    }

    public function testHasReturnsFalseForInvalidIdsWithoutEvaluatingAutowirePolicy(): void
    {
        $container = new Container(config: []);

        self::assertFalse($container->has(''));
        self::assertFalse($container->has(' service.id'));
        self::assertFalse($container->has('service id'));
        self::assertFalse($container->has("service\nid"));
    }

    public function testHasReturnsFalseForUnknownNonClassIdsWithoutEvaluatingAutowirePolicy(): void
    {
        $container = new Container(config: []);

        self::assertFalse($container->has('unknown.service'));
        self::assertFalse($container->has('not-a-class'));
    }

    public function testHasReturnsTrueForExplicitDefinitionsAndInstancesWithoutEvaluatingAutowirePolicy(): void
    {
        $container = new Container(
            definitions: [
                'test.definition' => static fn (): object => new \stdClass(),
            ],
            instances: [
                'test.instance' => new \stdClass(),
            ],
            config: [],
        );

        self::assertTrue($container->has('test.definition'));
        self::assertTrue($container->has('test.instance'));
    }

    public function testHasReturnsTrueForUnregisteredInstantiableConcreteClassWhenStrictAutowirePolicyAllowsIt(): void
    {
        $container = new Container(config: self::validAutowireConfig());

        self::assertTrue($container->has(ContainerStrictConfigConcreteFixture::class));
    }

    public function testHasReturnsFalseForUnregisteredConcreteClassWhenConcreteAutowireIsDisabled(): void
    {
        $container = new Container(
            config: self::validAutowireConfig(
                autowireConcrete: false,
                allowReflectionForConcrete: true,
            ),
        );

        self::assertFalse($container->has(ContainerStrictConfigConcreteFixture::class));
    }

    public function testHasReturnsFalseForUnregisteredConcreteClassWhenReflectionForConcreteIsDisabled(): void
    {
        $container = new Container(
            config: self::validAutowireConfig(
                autowireConcrete: true,
                allowReflectionForConcrete: false,
            ),
        );

        self::assertFalse($container->has(ContainerStrictConfigConcreteFixture::class));
    }

    /**
     * @return iterable<string, array{
     *     config: array<string, mixed>,
     *     expectedMessage: string
     * }>
     */
    public static function invalidAutowirePolicyConfigProvider(): iterable
    {
        yield 'foundation-missing' => [
            'config' => [],
            'expectedMessage' => 'container-config-foundation-missing',
        ];

        yield 'foundation-not-map' => [
            'config' => [
                'foundation' => true,
            ],
            'expectedMessage' => 'container-config-foundation-missing',
        ];

        yield 'foundation-container-missing' => [
            'config' => [
                'foundation' => [],
            ],
            'expectedMessage' => 'container-config-foundation-container-missing',
        ];

        yield 'foundation-container-not-map' => [
            'config' => [
                'foundation' => [
                    'container' => true,
                ],
            ],
            'expectedMessage' => 'container-config-foundation-container-missing',
        ];

        yield 'foundation-container-empty' => [
            'config' => [
                'foundation' => [
                    'container' => [],
                ],
            ],
            'expectedMessage' => 'container-config-foundation-container-invalid',
        ];

        yield 'autowire-concrete-missing' => [
            'config' => [
                'foundation' => [
                    'container' => [
                        'allow_reflection_for_concrete' => true,
                    ],
                ],
            ],
            'expectedMessage' => 'container-config-foundation-container-invalid',
        ];

        yield 'allow-reflection-for-concrete-missing' => [
            'config' => [
                'foundation' => [
                    'container' => [
                        'autowire_concrete' => true,
                    ],
                ],
            ],
            'expectedMessage' => 'container-config-foundation-container-invalid',
        ];

        yield 'autowire-concrete-not-bool' => [
            'config' => [
                'foundation' => [
                    'container' => [
                        'autowire_concrete' => 'true',
                        'allow_reflection_for_concrete' => true,
                    ],
                ],
            ],
            'expectedMessage' => 'container-config-foundation-container-invalid',
        ];

        yield 'allow-reflection-for-concrete-not-bool' => [
            'config' => [
                'foundation' => [
                    'container' => [
                        'autowire_concrete' => true,
                        'allow_reflection_for_concrete' => 'true',
                    ],
                ],
            ],
            'expectedMessage' => 'container-config-foundation-container-invalid',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function validAutowireConfig(
        bool $autowireConcrete = true,
        bool $allowReflectionForConcrete = true,
    ): array {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => $autowireConcrete,
                    'allow_reflection_for_concrete' => $allowReflectionForConcrete,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function assertCanAutowireFailsWith(array $config, string $expectedMessage): void
    {
        $container = new Container(config: $config);

        try {
            $container->canAutowire(ContainerStrictConfigConcreteFixture::class);
        } catch (ContainerException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
            self::assertSame(ContainerException::ERROR_CODE, $exception->errorCode());

            return;
        }

        self::fail('Expected canAutowire() to fail with message: ' . $expectedMessage);
    }
}

final class ContainerStrictConfigConcreteFixture
{
}
