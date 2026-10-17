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

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class NormalCommandsBootKernelOperationsHostTest extends CliIntegrationTestCase
{
    public function testCanonicalHostReturnsConsolePlanAndResolvesCliOnlyAfterModuleSelection(): void
    {
        $root = self::consumer();
        try {
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $plan = $host->get(ModulePlan::class);
            self::assertInstanceOf(ModulePlan::class, $plan);
            self::assertSame('console', $plan->app());
            self::assertTrue($plan->hasEnabledModule('platform.cli'));
            self::assertTrue($plan->hasEnabledModule('platform.redaction'));
            self::assertInstanceOf(KernelOpsInterface::class, $host->get(KernelOpsInterface::class));
            self::assertInstanceOf(CliApplication::class, $host->get(CliApplication::class));
            self::assertSame(0, self::execute($root . '/vendor/bin/coretsia', ['list'])['exit']);
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testExcludedModuleCannotBeResurrectedByConcreteClassAutowiring(): void
    {
        $root = self::consumer();
        try {
            self::excludeCli($root);
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $plan = $host->get(ModulePlan::class);
            self::assertFalse($plan->hasEnabledModule('platform.cli'));
            $result = self::execute($root . '/vendor/bin/coretsia', ['list']);
            self::assertSafeFailure($result, 'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed');
            $source = (string) \file_get_contents(self::packageRoot() . '/src/Bootstrap/CliHostBootstrap.php');
            $check = \strpos($source, '!$plan->hasEnabledModule(\'platform.cli\')');
            $application = \strpos($source, '$container->get(CliApplication::class)');
            self::assertIsInt($check);
            self::assertIsInt($application);
            self::assertLessThan($application, $check);

            self::assertSame(1, \substr_count($source, 'new KernelOpsHostBooter()'));
            self::assertSame(1, \substr_count($source, '->boot('));
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }
}
