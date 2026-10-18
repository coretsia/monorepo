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

namespace Coretsia\Platform\Cli\Bootstrap\Entrypoint;

use Coretsia\Platform\Cli\Bootstrap\CliEntrypointPathsResolver;
use Coretsia\Platform\Cli\Bootstrap\CliHostBootstrap;
use Coretsia\Platform\Cli\Bootstrap\UltraEarlyDoctorRunner;
use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Exception\CliBootstrapException;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Output\FormattedOutput;
use Coretsia\Platform\Cli\Output\TerminalCapabilitiesDetector;

/**
 * Package-owned composition root for the CLI executable.
 *
 * @return callable(string, string, ?string, ?string, mixed, mixed, mixed): int
 */
return static function (
    string $launcherFile,
    string $autoloadPath,
    ?string $composerBinDir,
    ?string $explicitApplicationRoot,
    mixed $argv,
    mixed $stdout,
    mixed $stderr,
): int {
    try {
        $writer = new ConsoleOutputWriter($stdout, $stderr);
        $doctor = \is_array($argv) && ($argv[1] ?? null) === DoctorCommand::NAME;

        if ($doctor) {
            $exitCode = new UltraEarlyDoctorRunner()->run($argv, $writer);
        } else {
            $paths = new CliEntrypointPathsResolver()->resolve(
                launcherFile: $launcherFile,
                autoloadPath: $autoloadPath,
                composerBinDir: $composerBinDir,
                explicitApplicationRoot: $explicitApplicationRoot,
            );
            $capabilities = new TerminalCapabilitiesDetector()->detect($stdout, $stderr);
            $exitCode = new CliHostBootstrap()->run(
                $paths->applicationRoot(),
                $argv,
                $capabilities,
                $writer,
            );
        }
    } catch (CliBootstrapException $exception) {
        try {
            new ConsoleOutputWriter($stdout, $stderr)->write(
                new FormattedOutput('', $exception->errorCode() . ': ' . $exception->reason()),
            );
        } catch (\Throwable) {
            // Post-autoload diagnostics have no alternate sink.
        }

        return 1;
    } catch (\Throwable) {
        try {
            new ConsoleOutputWriter($stdout, $stderr)->write(
                new FormattedOutput('', 'CORETSIA_CLI_UNCAUGHT_EXCEPTION: uncaught-exception'),
            );
        } catch (\Throwable) {
            // Post-autoload diagnostics have no alternate sink.
        }

        return 1;
    }

    return $exitCode;
};
