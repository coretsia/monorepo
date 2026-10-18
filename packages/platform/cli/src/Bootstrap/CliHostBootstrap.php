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

namespace Coretsia\Platform\Cli\Bootstrap;

use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Output\FormattedOutput;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;

/**
 * The only CLI boundary that boots the canonical Kernel source operations host.
 *
 * @internal
 */
final class CliHostBootstrap
{
    /** @param array<array-key, mixed> $argv */
    public function run(
        string $applicationRoot,
        array $argv,
        TerminalCapabilities $capabilities,
        ConsoleOutputWriter $writer,
    ): int {
        try {
            $container = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($applicationRoot));
            $plan = $container->get(ModulePlan::class);

            if (
                !$plan instanceof ModulePlan
                || $plan->app() !== 'console'
                || !$plan->hasEnabledModule('platform.cli')
            ) {
                throw new \UnexpectedValueException('cli-source-host-module-plan-invalid');
            }

            $application = $container->get(CliApplication::class);
            if (!$application instanceof CliApplication) {
                throw new \UnexpectedValueException('cli-source-host-application-invalid');
            }
        } catch (\Throwable) {
            $writer->write(new FormattedOutput('', 'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed'));

            return 1;
        }

        return $application->run($argv, $capabilities, $writer);
    }
}
