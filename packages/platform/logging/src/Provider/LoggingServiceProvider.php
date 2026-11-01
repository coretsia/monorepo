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

namespace Coretsia\Platform\Logging\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\AppTargetProviderInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Declarative PSR-3 logger binding for the selected platform.logging module.
 *
 * The provider declares only service references and the stateless runtime
 * factory. Kernel owns module selection and the canonical application target;
 * configuration validation, destination initialization, and all I/O occur
 * only when the LoggerInterface service is actually constructed or used.
 */
final class LoggingServiceProvider implements
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
            ->requireService(AppTargetProviderInterface::class)
            ->requireService(ConfigRepositoryInterface::class)
            ->requireService(ContextAccessorInterface::class)
            ->requireService(CorrelationIdProviderInterface::class)
            ->requireService(SensitiveDataRedactorInterface::class)
            ->requireService(SensitiveKeyClassifierInterface::class)
            ->requireService(ClockInterface::class)
            ->classMethodFactory(
                id: LoggerInterface::class,
                factoryClass: LoggingServiceFactory::class,
                method: 'createLogger',
                arguments: [
                    ContainerValueReference::service(ConfigRepositoryInterface::class),
                    ContainerValueReference::service(ContextAccessorInterface::class),
                    ContainerValueReference::service(CorrelationIdProviderInterface::class),
                    ContainerValueReference::service(SensitiveDataRedactorInterface::class),
                    ContainerValueReference::service(SensitiveKeyClassifierInterface::class),
                    ContainerValueReference::service(ClockInterface::class),
                    ContainerValueReference::service(AppTargetProviderInterface::class),
                ],
            );
    }
}
