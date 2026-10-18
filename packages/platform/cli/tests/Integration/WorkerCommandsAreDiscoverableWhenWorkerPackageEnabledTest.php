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

use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkerComposedTestCase;
use Coretsia\Platform\Worker\Module\WorkerModule;

final class WorkerCommandsAreDiscoverableWhenWorkerPackageEnabledTest extends WorkerComposedTestCase
{
    public function testConsolePlanEnablesWorkerProviderAndCatalogContainsFourWorkerCommands(): void
    {
        $root = self::workerConsumer();
        try {
            [$plan, $catalog, $registry] = self::composedWorkerHost($root);
            self::assertTrue($plan->hasEnabledModule(WorkerModule::MODULE_ID));
            self::assertWorkerTags($catalog, $registry);
            self::assertNoWorkerImportsInCli();
            $result = self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']);
            self::assertSame(0, $result['exit'], $result['stderr']);
            $document = self::jsonRecord($result['stdout']);
            $groups = $document['data']['records'][0]['payload']['groups'];
            $names = \array_column($groups['worker'], 'name');
            foreach (self::workerCommands() as $command) {
                self::assertContains($command::NAME, $names);
            }
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testInstalledWorkerPackageIsNotImplicitlyEnabled(): void
    {
        $root = self::consumer();
        try {
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $plan = $host->get(ModulePlan::class);
            self::assertFalse($plan->hasEnabledModule(WorkerModule::MODULE_ID));
            $catalog = $host->get(CommandCatalog::class);
            foreach (self::workerCommands() as $command) {
                self::assertArrayNotHasKey($command::NAME, $catalog->descriptors());
            }
        } finally {
            self::removeTree($root);
        }
    }
}
