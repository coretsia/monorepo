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

namespace Coretsia\Platform\Http\Tests\Unit;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class HttpPsrFactorySelectionTest extends TestCase
{
    /**
     * @param array{response: string, stream: string} $expected
     */
    #[DataProvider('builtInSelections')]
    public function testExactFixedMappingAndOnlySelectedFactoryDefinitions(
        string $selection,
        array $expected,
    ): void {
        self::assertSame($expected, HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES[$selection]);
        $set = self::definitions($selection);
        $factories = self::factoryServices($set);
        $aliases = self::factoryAliases($set);

        $selected = \array_values(\array_unique(\array_values($expected)));
        self::assertSame($selected, \array_keys($factories));
        foreach ($selected as $class) {
            self::assertSame('service.class', $factories[$class]['kind']);
            self::assertSame($class, $factories[$class]['class']);
            self::assertSame([], $factories[$class]['arguments']);
            self::assertTrue($factories[$class]['shared']);
        }

        self::assertSame([
            ResponseFactoryInterface::class => $expected['response'],
            StreamFactoryInterface::class => $expected['stream'],
        ], $aliases);
        self::assertContains(ResponseFactoryInterface::class, $set->requiredServiceIds());
        self::assertContains(StreamFactoryInterface::class, $set->requiredServiceIds());

        foreach (HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES as $other => $classes) {
            if ($other === $selection) {
                continue;
            }
            foreach ($classes as $class) {
                if (!\in_array($class, $selected, true)) {
                    self::assertArrayNotHasKey($class, $factories);
                }
            }
        }
    }

    /** @return iterable<string, array{string, array{response: string, stream: string}}> */
    public static function builtInSelections(): iterable
    {
        yield 'nyholm' => [
            'nyholm',
            [
                'response' => 'Nyholm\\Psr7\\Factory\\Psr17Factory',
                'stream' => 'Nyholm\\Psr7\\Factory\\Psr17Factory',
            ],
        ];
        yield 'guzzlehttp' => [
            'guzzlehttp',
            [
                'response' => 'GuzzleHttp\\Psr7\\HttpFactory',
                'stream' => 'GuzzleHttp\\Psr7\\HttpFactory',
            ],
        ];
        yield 'laminas' => [
            'laminas',
            [
                'response' => 'Laminas\\Diactoros\\ResponseFactory',
                'stream' => 'Laminas\\Diactoros\\StreamFactory',
            ],
        ];
        yield 'slim' => [
            'slim',
            [
                'response' => 'Slim\\Psr7\\Factory\\ResponseFactory',
                'stream' => 'Slim\\Psr7\\Factory\\StreamFactory',
            ],
        ];
    }

    public function testSharedFactoryModesHaveOneServiceAndTwoAliases(): void
    {
        foreach (['nyholm', 'guzzlehttp'] as $selection) {
            $set = self::definitions($selection);
            $services = self::factoryServices($set);
            $aliases = self::factoryAliases($set);

            self::assertCount(1, $services);
            self::assertSame($aliases[ResponseFactoryInterface::class], $aliases[StreamFactoryInterface::class]);
            self::assertSame(\array_key_first($services), $aliases[ResponseFactoryInterface::class]);
        }
    }

    public function testSplitFactoryModesHaveTwoIndependentServicesAndAliases(): void
    {
        foreach (['laminas', 'slim'] as $selection) {
            $set = self::definitions($selection);
            $services = self::factoryServices($set);
            $aliases = self::factoryAliases($set);

            self::assertCount(2, $services);
            self::assertNotSame($aliases[ResponseFactoryInterface::class], $aliases[StreamFactoryInterface::class]);
            self::assertArrayHasKey($aliases[ResponseFactoryInterface::class], $services);
            self::assertArrayHasKey($aliases[StreamFactoryInterface::class], $services);
        }
    }

    public function testExternalContributesNoConcreteFactoryOrAliasesButRequiresBothPorts(): void
    {
        $set = self::definitions('external');
        self::assertSame([], self::factoryServices($set));
        self::assertSame([], self::factoryAliases($set));
        self::assertContains(ResponseFactoryInterface::class, $set->requiredServiceIds());
        self::assertContains(StreamFactoryInterface::class, $set->requiredServiceIds());
    }

    public function testDefinitionProductionNeverAutoloadsConcreteVendorFactories(): void
    {
        $attempts = [];
        $observer = static function (string $class) use (&$attempts): void {
            foreach (['Nyholm\\', 'GuzzleHttp\\', 'Laminas\\', 'Slim\\'] as $prefix) {
                if (\str_starts_with($class, $prefix)) {
                    $attempts[] = $class;
                }
            }
        };

        \spl_autoload_register($observer, prepend: true);
        try {
            foreach (['external', 'nyholm', 'guzzlehttp', 'laminas', 'slim'] as $selection) {
                self::assertNotEmpty(self::definitions($selection)->toDescriptorStream());
            }
        } finally {
            \spl_autoload_unregister($observer);
        }

        self::assertSame([], $attempts);
        $source = \file_get_contents(new \ReflectionClass(HttpServiceProvider::class)->getFileName());
        self::assertIsString($source);
        foreach (
            [
                'class_exists(',
                'interface_exists(',
                'new \\ReflectionClass(',
                'new \\ReflectionMethod(',
                'file_exists(',
                'is_file(',
                'scandir(',
                'glob(',
                'shell_exec(',
                'Composer\\',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    /** @param mixed $selection */
    #[DataProvider('invalidSelections')]
    public function testInvalidFactorySelectionsFailAtCanonicalConfigValidation(mixed $selection): void
    {
        $http = self::defaults();
        $http['factory'] = $selection;
        $rules = require __DIR__ . '/../../config/rules.php';
        self::assertIsArray($rules);

        $result = new ConfigValidator()->validate(
            ['http' => $http],
            [ConfigRuleset::fromArray('http', $rules)],
        );
        self::assertTrue($result->isFailure());
        self::assertNotEmpty($result->violations());
        self::assertSame('factory', $result->violations()[0]->path());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidSelections(): iterable
    {
        yield 'auto' => ['auto'];
        yield 'empty' => [''];
        yield 'uppercase' => ['NYHOLM'];
        yield 'unknown' => ['custom'];
        yield 'whitespace' => [' nyholm'];
        yield 'integer' => [7];
        yield 'null' => [null];
        yield 'boolean' => [true];
    }

    public function testChangingSelectionChangesDeterministicFactoryDescriptorsOnly(): void
    {
        $nyholm = self::definitions('nyholm')->toDescriptorStream();
        $slim = self::definitions('slim')->toDescriptorStream();
        self::assertNotSame(
            self::factoryServices(self::definitions('nyholm')),
            self::factoryServices(self::definitions('slim')),
        );

        $withoutFactories = static fn (array $operations): array => \array_values(
            \array_filter(
                $operations,
                static fn (array $op): bool => !\in_array($op['id'] ?? null, [
                        'Nyholm\\Psr7\\Factory\\Psr17Factory',
                        'GuzzleHttp\\Psr7\\HttpFactory',
                        'Laminas\\Diactoros\\ResponseFactory',
                        'Laminas\\Diactoros\\StreamFactory',
                        'Slim\\Psr7\\Factory\\ResponseFactory',
                        'Slim\\Psr7\\Factory\\StreamFactory',
                    ], true) && !\in_array($op['alias'] ?? null, [
                        ResponseFactoryInterface::class,
                        StreamFactoryInterface::class,
                    ], true),
            ),
        );

        self::assertSame($withoutFactories($nyholm), $withoutFactories($slim));
    }

    public function testLaterBindingWinsWithoutProviderLocalCollisionRejection(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define(
            $definitions,
            new ContainerDefinitionContext(['http' => \array_replace(self::defaults(), ['factory' => 'nyholm'])]),
        );
        $definitions->classService('App\\OverrideResponseFactory', \stdClass::class);
        $definitions->alias(ResponseFactoryInterface::class, 'App\\OverrideResponseFactory');

        $set = $definitions->build();
        $aliases = [];
        foreach ($set->toDescriptorStream() as $operation) {
            if (($operation['alias'] ?? null) === ResponseFactoryInterface::class) {
                $aliases[] = $operation['serviceId'];
            }
        }

        self::assertSame([
            'Nyholm\\Psr7\\Factory\\Psr17Factory',
            'App\\OverrideResponseFactory',
        ], $aliases);

        $container = new ContainerBuilder()
            ->applyDefinitions($set)
            ->build();

        self::assertInstanceOf(\stdClass::class, $container->get(ResponseFactoryInterface::class));
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        $http = require __DIR__ . '/../../config/http.php';
        self::assertIsArray($http);

        return $http;
    }

    private static function definitions(string $selection): ContainerDefinitionSet
    {
        $http = self::defaults();
        $http['factory'] = $selection;
        $definitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($definitions, new ContainerDefinitionContext(['http' => $http]));

        return $definitions->build();
    }

    /** @return array<string, array<string, mixed>> */
    private static function factoryServices(ContainerDefinitionSet $set): array
    {
        $selected = [];
        foreach (HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES as $pair) {
            foreach ($pair as $class) {
                $selected[$class] = true;
            }
        }

        $services = [];
        foreach ($set->toDescriptorStream() as $operation) {
            $id = $operation['id'] ?? null;
            if (\is_string($id) && isset($selected[$id])) {
                $services[$id] = $operation;
            }
        }

        return $services;
    }

    /** @return array<string, string> */
    private static function factoryAliases(ContainerDefinitionSet $set): array
    {
        $aliases = [];
        foreach ($set->toDescriptorStream() as $operation) {
            $id = $operation['alias'] ?? null;
            if ($id === ResponseFactoryInterface::class || $id === StreamFactoryInterface::class) {
                $aliases[$id] = $operation['serviceId'];
            }
        }

        return $aliases;
    }
}
