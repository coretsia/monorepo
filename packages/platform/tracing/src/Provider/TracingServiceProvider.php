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

namespace Coretsia\Platform\Tracing\Provider;

use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation;

/**
 * Declarative W3C propagation binding for the selected platform.tracing module.
 *
 * Kernel owns target-local module selection and provider ordering. Foundation
 * retains the noop fallback when this module is not selected, and retains the
 * tracer/span lifecycle independently of this provider.
 *
 * Registration and definition construction perform no configuration reads,
 * service resolution, carrier processing, or runtime initialization.
 */
final class TracingServiceProvider implements
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
        $definitions->classService(
            id: ContextPropagationInterface::class,
            class: W3cTraceContextPropagation::class,
        );
    }
}
