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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Tests\Integration\Support\ExternalOwnerCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\ExternalOwnerServiceProvider;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkerComposedTestCase;

final class ExternalTaggedCommandDiscoveryTest extends WorkerComposedTestCase
{
    public function testEnabledOwnerProviderContributesThroughReservedTagOnly(): void
    {
        $builder = new ContainerBuilder();
        $builder->registerProviders([new ExternalOwnerServiceProvider()]);
        $ids = \array_map(
            static fn ($entry): string => $entry->id(),
            $builder->tagRegistry()->all(ReservedTags::CLI_COMMAND),
        );
        self::assertSame([ExternalOwnerCommand::class], $ids);
        $catalog = new CommandCatalog(
            $builder->tagRegistry(),
            new CommandTagSchema(),
            new CommandOverrides(),
            [],
            [],
        );
        self::assertSame(ExternalOwnerCommand::class, $catalog->require(ExternalOwnerCommand::NAME)->serviceId());
        self::assertSame(ExternalOwnerCommand::NAME, $builder->build()->get(ExternalOwnerCommand::class)->name());
        $source = (string) \file_get_contents(self::packageRoot() . '/src/Catalog/CommandCatalog.php');
        self::assertStringContainsString('ReservedTags::CLI_COMMAND', $source);
        self::assertStringNotContainsString('glob(', $source);
        self::assertStringNotContainsString('scandir(', $source);
        self::assertStringNotContainsString('cli.commands.list', $source);
    }

    public function testEnabledExternalModuleIsDiscoveredThroughCanonicalHostTags(): void
    {
        $root = self::workerConsumer();
        try {
            [$plan, $catalog, $registry] = self::composedWorkerHost($root);
            self::assertTrue($plan->hasEnabledModule('platform.worker'));
            self::assertWorkerTags($catalog, $registry);
            self::assertNoWorkerImportsInCli();
        } finally {
            self::removeTree($root);
        }
    }
}
