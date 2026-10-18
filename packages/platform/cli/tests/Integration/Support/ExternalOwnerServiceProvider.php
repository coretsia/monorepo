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

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;

/** Test-only representation of an enabled independent package's provider. */
final class ExternalOwnerServiceProvider implements ServiceProviderInterface, ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();
        $builder->registerDefinitionProvider($this);
    }

    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        $definitions
            ->classService(ExternalOwnerService::class, ExternalOwnerService::class)
            ->classService(
                ExternalOwnerCommand::class,
                ExternalOwnerCommand::class,
                [ContainerValueReference::service(ExternalOwnerService::class)],
            )
            ->tag(ReservedTags::CLI_COMMAND, ExternalOwnerCommand::class, meta: [
                'name' => ExternalOwnerCommand::NAME,
                'summary' => ExternalOwnerCommand::SUMMARY,
                'group' => ExternalOwnerCommand::GROUP,
                'hidden' => ExternalOwnerCommand::HIDDEN,
                'arguments' => ExternalOwnerCommand::ARGUMENTS,
                'options' => ExternalOwnerCommand::OPTIONS,
            ]);
    }
}
