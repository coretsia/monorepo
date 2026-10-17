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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;

final class ReservedBoundaryHelpServiceProvider implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();
        $builder->factory(
            ReservedBoundaryHelpCommand::class,
            static fn (Container $container): ReservedBoundaryHelpCommand => new ReservedBoundaryHelpCommand(),
        );
        $builder->tag(ReservedTags::CLI_COMMAND, ReservedBoundaryHelpCommand::class, meta: [
            'name' => ReservedBoundaryHelpCommand::NAME,
            'summary' => ReservedBoundaryHelpCommand::SUMMARY,
            'group' => ReservedBoundaryHelpCommand::GROUP,
            'hidden' => ReservedBoundaryHelpCommand::HIDDEN,
            'arguments' => ReservedBoundaryHelpCommand::ARGUMENTS,
            'options' => ReservedBoundaryHelpCommand::OPTIONS,
        ]);
        $builder->registerDefinitionProvider($this);
    }

    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
    }
}
