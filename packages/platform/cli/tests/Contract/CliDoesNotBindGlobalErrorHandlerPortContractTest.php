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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Mapper\CliExceptionMapper;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use PHPUnit\Framework\TestCase;

final class CliDoesNotBindGlobalErrorHandlerPortContractTest extends TestCase
{
    public function testCliConsumesCanonicalHandlerWithoutBindingOrOverridingIt(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new CliServiceProvider()->define($definitions, new ContainerDefinitionContext([]));
        $set = $definitions->build();
        $operations = $set->toDescriptorStream();

        self::assertContains(ErrorHandlerInterface::class, $set->requiredServiceIds());
        self::assertNotContains(CliExceptionMapper::class, $set->requiredServiceIds());

        $services = [];
        $mapperTags = [];
        foreach ($operations as $operation) {
            if (isset($operation['id'])) {
                $services[$operation['id']] = $operation;
            }
            self::assertNotSame(ErrorHandlerInterface::class, $operation['alias'] ?? null);
            self::assertNotSame(ErrorHandlerInterface::class, $operation['id'] ?? null);
            if (($operation['tag'] ?? null) === ReservedTags::ERROR_MAPPER) {
                $mapperTags[] = $operation;
            }
        }

        self::assertArrayHasKey(CliExceptionMapper::class, $services);
        self::assertSame('service.class', $services[CliExceptionMapper::class]['kind']);
        self::assertArrayHasKey(CliApplication::class, $services);
        self::assertArrayNotHasKey(ErrorHandler::class, $services);
        self::assertSame(
            ['id' => ErrorHandlerInterface::class, 'type' => 'service'],
            $services[CliApplication::class]['arguments'][6],
        );
        self::assertCount(1, $mapperTags);
        self::assertSame(CliExceptionMapper::class, $mapperTags[0]['serviceId']);
        self::assertSame(100, $mapperTags[0]['priority']);
        self::assertSame([], $mapperTags[0]['meta']);

        $constructor = new \ReflectionMethod(CliApplication::class, '__construct');
        self::assertSame(
            ErrorHandlerInterface::class,
            $constructor->getParameters()[6]->getType()?->getName(),
        );
    }

    public function testSourceProviderDoesNotIntroduceGlobalHandlerAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(new CliServiceProvider());
        $container = $builder->build();

        self::assertFalse($container->has(ErrorHandlerInterface::class));
        self::assertNotContains(ErrorHandler::class, $builder->serviceIds());
        self::assertTrue($container->has(CliExceptionMapper::class));

        $mappers = $builder->tagRegistry()->all(ReservedTags::ERROR_MAPPER);
        self::assertCount(1, $mappers);
        self::assertSame(CliExceptionMapper::class, $mappers[0]->id());
        self::assertSame(100, $mappers[0]->priority());
        self::assertSame([], $mappers[0]->meta());
    }

    public function testCanonicalProviderCompositionSuppliesExactlyOneHandlerBinding(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext([]);
        new ErrorsServiceProvider()->define($definitions, $context);
        new CliServiceProvider()->define($definitions, $context);

        $aliases = [];
        $handlers = [];
        foreach ($definitions->build()->toDescriptorStream() as $operation) {
            if (($operation['alias'] ?? null) === ErrorHandlerInterface::class) {
                $aliases[] = $operation;
            }
            if (($operation['id'] ?? null) === ErrorHandler::class) {
                $handlers[] = $operation;
            }
        }
        self::assertCount(1, $aliases);
        self::assertSame(ErrorHandler::class, $aliases[0]['serviceId']);
        self::assertCount(1, $handlers);

        $builder = new ContainerBuilder();
        $builder->registerProviders([
            new ErrorsServiceProvider(),
            new CliServiceProvider(),
        ]);
        $container = $builder->build();
        self::assertTrue($container->has(ErrorHandlerInterface::class));
        self::assertTrue($container->has(ErrorHandler::class));
        self::assertTrue($container->has(CliExceptionMapper::class));
    }
}
