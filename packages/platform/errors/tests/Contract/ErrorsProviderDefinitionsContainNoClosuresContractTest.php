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

namespace Coretsia\Platform\Errors\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use PHPUnit\Framework\TestCase;

final class ErrorsProviderDefinitionsContainNoClosuresContractTest extends TestCase
{
    public function testProviderEmitsOnlyCanonicalSerializableDescriptorsAndRequiredServicePorts(): void
    {
        $builder = new ContainerDefinitionBuilder();
        new ErrorsServiceProvider()->define($builder, new ContainerDefinitionContext([]));
        $set = $builder->build();
        $operations = $set->toDescriptorStream();

        self::assertCount(4, $operations);
        self::assertSame([
            'service.class',
            'service.factory.class-method',
            'service.factory.class-method',
            'alias',
        ], \array_column($operations, 'kind'));
        self::assertSame([
            DefaultExceptionMapper::class,
            ExceptionMapperRegistry::class,
            ErrorHandler::class,
        ], \array_slice(\array_column($operations, 'id'), 0, 3));
        self::assertSame(ErrorHandlerInterface::class, $operations[3]['alias']);
        self::assertSame(ErrorHandler::class, $operations[3]['serviceId']);

        $required = $set->requiredServiceIds();
        self::assertContains(SensitiveDataRedactorInterface::class, $required);
        self::assertContains(ErrorReporterPortInterface::class, $required);
        self::assertNotContains(DefaultExceptionMapper::class, $required);
        self::assertNotContains(ErrorHandler::class, $required);

        self::assertSame(
            $operations,
            ContainerDefinitionSet::fromValidatedState($operations, $required)->toDescriptorStream(),
        );
        self::assertIsString(
            \json_encode(['operations' => $operations, 'required' => $required], \JSON_THROW_ON_ERROR),
        );
        self::assertNoRuntimeValues($operations);
    }

    public function testProviderDefinitionsDoNotCreateRuntimeServicesOrResolveDependencies(): void
    {
        $builder = new ContainerDefinitionBuilder();
        $provider = new ErrorsServiceProvider();
        $provider->define($builder, new ContainerDefinitionContext([]));
        $first = $builder->build();
        $secondBuilder = new ContainerDefinitionBuilder();
        $provider->define(
            $secondBuilder,
            new ContainerDefinitionContext(['errors' => ['reporting' => ['enabled' => true]]]),
        );

        self::assertSame($first->toDescriptorStream(), $secondBuilder->build()->toDescriptorStream());
        self::assertSame($first->requiredServiceIds(), $secondBuilder->build()->requiredServiceIds());
    }

    private static function assertNoRuntimeValues(mixed $value): void
    {
        if (\is_array($value)) {
            foreach ($value as $item) {
                self::assertNoRuntimeValues($item);
            }

            return;
        }

        self::assertTrue(
            $value === null || \is_string($value) || \is_int($value) || \is_bool($value),
            'Declarative descriptors must not contain closures, runtime objects, floats, or resources.',
        );
    }
}
