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

namespace Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;

/**
 * Declarative test-package contribution through the canonical CLI command tag.
 */
final class ExternalCommandServiceProvider implements ServiceProviderInterface, ContainerDefinitionProviderInterface
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
            ->classService(ExternalOwnerService::class, ExternalOwnerService::class)
            ->classService(
                ExternalModeCommand::class,
                ExternalModeCommand::class,
                [ContainerValueReference::service(ExternalOwnerService::class)],
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                ExternalModeCommand::class,
                meta: [
                    'name' => ExternalModeCommand::NAME,
                    'summary' => ExternalModeCommand::SUMMARY,
                    'group' => ExternalModeCommand::GROUP,
                    'hidden' => ExternalModeCommand::HIDDEN,
                    'arguments' => ExternalModeCommand::ARGUMENTS,
                    'options' => ExternalModeCommand::OPTIONS,
                ],
            );
    }
}
