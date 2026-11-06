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

namespace Coretsia\Platform\ProblemDetails\Provider;

use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Declarative HTTP Problem Details services for explicitly selected targets.
 *
 * Error normalization belongs to platform.errors; response and stream
 * factories belong to the selected compatible HTTP runtime. Only the
 * existing system-pre middleware tag is contributed by this package.
 */
final class ProblemDetailsServiceProvider implements
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
            ->requireService(ErrorHandlerInterface::class)
            ->requireService(ResponseFactoryInterface::class)
            ->requireService(StreamFactoryInterface::class)
            ->classService(
                ErrorDescriptorToProblemDetailsAdapter::class,
                ErrorDescriptorToProblemDetailsAdapter::class,
            )
            ->classService(
                ProblemDetailsJsonRenderer::class,
                ProblemDetailsJsonRenderer::class,
            )
            ->classService(
                HttpErrorHandlingMiddleware::class,
                HttpErrorHandlingMiddleware::class,
                arguments: [
                    ContainerValueReference::service(ErrorHandlerInterface::class),
                    ContainerValueReference::service(ErrorDescriptorToProblemDetailsAdapter::class),
                    ContainerValueReference::service(ProblemDetailsJsonRenderer::class),
                    ContainerValueReference::service(ResponseFactoryInterface::class),
                    ContainerValueReference::service(StreamFactoryInterface::class),
                ],
            )
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                HttpErrorHandlingMiddleware::class,
                priority: 1000,
                meta: [],
            );
    }
}
