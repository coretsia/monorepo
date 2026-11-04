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

namespace Coretsia\Platform\Metrics\Provider;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Metrics\Default\DefaultMeter;
use Coretsia\Platform\Metrics\Noop\NullMetricsRenderer;

/**
 * Declarative noop-safe metrics bindings for the selected platform.metrics module.
 *
 * Kernel owns target-local module selection and provider ordering. Foundation
 * retains the canonical NoopMeter fallback when this module is not selected.
 *
 * Registration and definition construction do not read configuration, resolve
 * services, access context, emit metrics, or initialize a recording backend.
 */
final class MetricsServiceProvider implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();
        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->classService(
                id: MeterPortInterface::class,
                class: DefaultMeter::class,
            )
            ->classService(
                id: MetricsRendererInterface::class,
                class: NullMetricsRenderer::class,
            );
    }
}
