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

namespace Coretsia\Kernel\Tests\Fixtures\PreExpansionPackage;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Tag\ReservedTags;
use Psr\Log\LoggerInterface;

final class PreExpansionFixtureServiceProvider implements ContainerDefinitionProviderInterface
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

        $fixtureConfig = $context->configRoot('pre_expansion');
        $seed = $fixtureConfig['seed'] ?? null;

        if (!\is_string($seed) || $seed === '') {
            throw new \LogicException('pre-expansion-fixture-seed-invalid');
        }

        $definitions
            ->parameter(
                'test.pre_expansion.seed',
                $seed,
            )
            ->classService(
                id: PreExpansionStatefulService::class,
                class: PreExpansionStatefulService::class,
                arguments: [
                    ContainerValueReference::parameter('test.pre_expansion.seed'),
                ],
                shared: true,
            )
            ->tag(
                tag: ReservedTags::KERNEL_STATEFUL,
                serviceId: PreExpansionStatefulService::class,
            )
            ->tag(
                tag: ReservedTags::KERNEL_RESET,
                serviceId: PreExpansionStatefulService::class,
            )
            ->classService(
                id: LoggerInterface::class,
                class: PreExpansionFailingObservability::class,
                shared: true,
            )
            ->classService(
                id: TracerPortInterface::class,
                class: PreExpansionFailingObservability::class,
                shared: true,
            )
            ->classService(
                id: MeterPortInterface::class,
                class: PreExpansionFailingObservability::class,
                shared: true,
            );
    }
}
