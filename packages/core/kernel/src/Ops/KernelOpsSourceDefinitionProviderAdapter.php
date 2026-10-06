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

namespace Coretsia\Kernel\Ops;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;

/**
 * Source-host registration adapter for a canonical definition-only provider.
 *
 * The adapter does not alter provider eligibility, order, definitions, tags,
 * aliases, parameters, or service contributions. It exists only so one
 * canonical declarative provider batch can be supplied to ContainerBuilder.
 *
 * @internal Kernel source-operations-host provider adapter.
 */
final readonly class KernelOpsSourceDefinitionProviderAdapter implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    public function __construct(
        private ContainerDefinitionProviderInterface $provider,
    ) {
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $this->provider->define(
            $definitions,
            $context,
        );
    }
}
