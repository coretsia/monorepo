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

use Coretsia\Platform\Cli\Bootstrap\UltraEarlyDoctorRunner;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class DoctorBypassesKernelOperationsHostTest extends CliIntegrationTestCase
{
    public function testDoctorSucceedsEvenWhenConsoleBootstrapConfigIsInvalid(): void
    {
        $root = self::consumer();
        try {
            self::write(
                $root . '/config/app.php',
                '<?php throw new \\RuntimeException("PRIVATE_KERNEL_BOOT_SENTINEL");',
            );
            $doctor = self::execute($root . '/vendor/bin/coretsia', ['doctor']);
            self::assertSame(0, $doctor['exit']);
            self::assertSame('', $doctor['stderr']);
            self::assertStringContainsString('Coretsia CLI — Runtime diagnostics', $doctor['stdout']);
            self::assertStringContainsString('Status: READY' . "\n", $doctor['stdout']);
            self::assertStringNotContainsString("\x1B", $doctor['stdout']);
            self::assertStringNotContainsString('PRIVATE_KERNEL_BOOT_SENTINEL', $doctor['stdout']);
            $normal = self::execute($root . '/vendor/bin/coretsia', ['list']);
            self::assertSafeFailure($normal, 'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed');
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testDoctorDispatchHasNoHostCatalogUowHooksOrResetDependency(): void
    {
        $entrypoint = (string) \file_get_contents(self::packageRoot() . '/src/Bootstrap/Entrypoint/cli.php');
        $doctor = (string) \file_get_contents(new \ReflectionClass(UltraEarlyDoctorRunner::class)->getFileName());
        self::assertStringContainsString('new UltraEarlyDoctorRunner()->run($argv, $writer)', $entrypoint);
        self::assertStringContainsString('new CliHostBootstrap()->run(', $entrypoint);
        foreach (
            [
                'KernelOpsHostBooter',
                'KernelRuntimeInterface',
                'runUnitOfWork(',
                'CommandCatalog',
                'ModulePlanResolver',
                'ResetOrchestrator',
                'TagRegistry',
                'ArtifactRuntimeBooter',
                'KernelOpsInterface',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $doctor);
        }
    }
}
