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

namespace Coretsia\Kernel\Tests\Fixtures\LifecycleFixturePackage;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Tag\ReservedTags;
use Psr\Log\LoggerInterface;

final class LifecycleFixtureServiceProvider implements ContainerDefinitionProviderInterface
{
    private static int $defineInvocations = 0;

    public static function resetInvocations(): void
    {
        self::$defineInvocations = 0;
    }

    public static function defineInvocations(): int
    {
        return self::$defineInvocations;
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        ++self::$defineInvocations;

        $fixtureConfig = $context->configRoot('lifecycle_fixture');
        $seed = $fixtureConfig['seed'] ?? null;

        if (!\is_string($seed) || $seed === '') {
            throw new \LogicException('lifecycle-fixture-seed-invalid');
        }

        $definitions
            ->parameter(
                'test.lifecycle_fixture.seed',
                $seed,
            )
            ->classService(
                id: LifecycleStatefulService::class,
                class: LifecycleStatefulService::class,
                arguments: [
                    ContainerValueReference::parameter('test.lifecycle_fixture.seed'),
                ],
                shared: true,
            )
            ->tag(
                tag: ReservedTags::KERNEL_STATEFUL,
                serviceId: LifecycleStatefulService::class,
            )
            ->tag(
                tag: ReservedTags::KERNEL_RESET,
                serviceId: LifecycleStatefulService::class,
            )
            ->classService(
                id: LoggerInterface::class,
                class: LifecycleFailingObservability::class,
                shared: true,
            )
            ->classService(
                id: TracerPortInterface::class,
                class: LifecycleFailingObservability::class,
                shared: true,
            )
            ->classService(
                id: MeterPortInterface::class,
                class: LifecycleFailingObservability::class,
                shared: true,
            );
    }
}
