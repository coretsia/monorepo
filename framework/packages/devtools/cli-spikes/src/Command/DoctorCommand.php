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

namespace Coretsia\Devtools\CliSpikes\Command;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrap;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrapFailedException;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesExitCodeMapper;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesPaths;
use Coretsia\Platform\Cli\Error\ErrorCodes as CliErrorCodes;
use Coretsia\Tools\Spikes\_support\DeterministicException;

final class DoctorCommand implements CommandInterface
{
    public function name(): string
    {
        return 'doctor';
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            $paths = SpikesPaths::fromServerGlobals();
            SpikesBootstrap::requireOnce($paths);

            $output->json([
                'command' => 'doctor',
                'ok' => true,
                'paths' => [
                    'repo_root' => '.',
                    'framework_root' => $paths->displayPath($paths->frameworkRoot()),
                    'spikes_fixtures_root' => $paths->displayPath($paths->spikesFixturesRoot()),
                    'spikes_bootstrap' => $paths->displayPath($paths->spikesBootstrapPath()),
                ],
            ]);

            return SpikesExitCodeMapper::success();
        } catch (SpikesBootstrapFailedException $e) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, $e->reason());

            return SpikesExitCodeMapper::failure();
        } catch (DeterministicException $e) {
            $output->error($e->code(), $e->getMessage());

            return SpikesExitCodeMapper::failure();
        } catch (\Throwable) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, 'uncaught-exception');

            return SpikesExitCodeMapper::failure();
        }
    }
}
