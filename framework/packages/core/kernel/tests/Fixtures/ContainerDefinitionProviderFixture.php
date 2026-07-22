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

namespace Coretsia\Kernel\Tests\Fixtures;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Psr\Clock\ClockInterface;

final class ContainerDefinitionProviderFixture implements ContainerDefinitionProviderInterface
{
    public const string PARAMETER_NAME = 'kernel.test.fixture.value';
    public const string PARAMETER_VALUE = 'container-fixture-value';
    public const string SERVICE_ALIAS = 'kernel.test.fixture.service';
    public const string SERVICE_TAG = 'kernel.test.fixture';

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $fixtureValue = $context->configRoot('custom')['container_fixture']['value'] ?? null;

        if (!\is_string($fixtureValue)) {
            throw new \LogicException('container-definition-fixture-config-invalid');
        }

        $definitions
            ->parameter(
                self::PARAMETER_NAME,
                $fixtureValue,
            )
            ->classService(
                ContainerDefinitionFixtureDependency::class,
                ContainerDefinitionFixtureDependency::class,
            )
            ->classService(
                id: ContainerDefinitionFixtureService::class,
                class: ContainerDefinitionFixtureService::class,
                arguments: [
                    ContainerValueReference::service(
                        ContainerDefinitionFixtureDependency::class,
                    ),
                    ContainerValueReference::parameter(
                        self::PARAMETER_NAME,
                    ),
                ],
            )
            ->classService(
                ContainerDefinitionFixtureClock::class,
                ContainerDefinitionFixtureClock::class,
            )
            ->alias(
                ClockInterface::class,
                ContainerDefinitionFixtureClock::class,
            )
            ->alias(
                self::SERVICE_ALIAS,
                ContainerDefinitionFixtureService::class,
            )
            ->tag(
                tag: self::SERVICE_TAG,
                serviceId: ContainerDefinitionFixtureService::class,
                priority: 25,
            );
    }
}

final class ContainerDefinitionFixtureDependency
{
}

final readonly class ContainerDefinitionFixtureService
{
    public function __construct(
        public ContainerDefinitionFixtureDependency $dependency,
        public string $value,
    ) {
    }
}

final class ContainerDefinitionFixtureClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('1970-01-01T00:00:00+00:00');
    }
}
