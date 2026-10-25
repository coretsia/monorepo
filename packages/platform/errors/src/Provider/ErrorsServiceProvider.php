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

namespace Coretsia\Platform\Errors\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Declarative wiring of the canonical Errors runtime services.
 *
 * Source and compiled containers consume the same service definitions.
 * Existing Foundation observability and platform.redaction bindings are
 * required and consumed, never replaced. Mapper contributions belong to
 * their owning packages; the fixed fallback is not tagged for discovery.
 */
final class ErrorsServiceProvider implements
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
            ->requireService(ConfigRepositoryInterface::class)
            ->requireService(ContainerInterface::class)
            ->requireService(TagRegistry::class)
            ->requireService(SensitiveDataRedactorInterface::class)
            ->requireService(ErrorReporterPortInterface::class)
            ->requireService(CorrelationIdProviderInterface::class)
            ->requireService(TracerPortInterface::class)
            ->requireService(MeterPortInterface::class)
            ->requireService(LoggerInterface::class)
            ->requireService(Stopwatch::class)
            ->classService(
                DefaultExceptionMapper::class,
                DefaultExceptionMapper::class,
            )
            ->classMethodFactory(
                id: ExceptionMapperRegistry::class,
                factoryClass: ErrorsServiceFactory::class,
                method: 'exceptionMapperRegistry',
                arguments: [
                    ContainerValueReference::service(TagRegistry::class),
                    ContainerValueReference::service(ContainerInterface::class),
                    ContainerValueReference::service(LoggerInterface::class),
                    ContainerValueReference::service(ConfigRepositoryInterface::class),
                ],
            )
            ->classMethodFactory(
                id: ErrorHandler::class,
                factoryClass: ErrorsServiceFactory::class,
                method: 'errorHandler',
                arguments: [
                    ContainerValueReference::service(ExceptionMapperRegistry::class),
                    ContainerValueReference::service(DefaultExceptionMapper::class),
                    ContainerValueReference::service(SensitiveDataRedactorInterface::class),
                    ContainerValueReference::service(ErrorReporterPortInterface::class),
                    ContainerValueReference::service(CorrelationIdProviderInterface::class),
                    ContainerValueReference::service(TracerPortInterface::class),
                    ContainerValueReference::service(MeterPortInterface::class),
                    ContainerValueReference::service(Stopwatch::class),
                    ContainerValueReference::service(ConfigRepositoryInterface::class),
                ],
            )
            ->alias(
                ErrorHandlerInterface::class,
                ErrorHandler::class,
            );
    }
}
