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

use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Output\FormattedOutput;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;

/**
 * Fixed dependency-free doctor path, outside the normal Kernel and catalog host.
 *
 * @internal
 */
final class UltraEarlyDoctorRunner
{
    /** @param array<array-key, mixed> $argv */
    public function run(array $argv, ConsoleOutputWriter $writer): int
    {
        $buffer = new CommandOutputBuffer();
        $exitCode = 1;

        try {
            if (
                !\array_is_list($argv)
                || \count($argv) !== 2
                || !\is_string($argv[0])
                || ($argv[1] ?? null) !== DoctorCommand::NAME
            ) {
                $buffer->error(
                    CliInputInvalidException::ERROR_CODE,
                    CliInputInvalidException::REASON_ARGUMENT_COUNT_INVALID,
                );
            } else {
                $input = new ArgvInput(
                    tokens: [DoctorCommand::NAME],
                    commandName: DoctorCommand::NAME,
                    arguments: [],
                    options: [],
                );
                $exitCode = new DoctorCommand()->run($input, $buffer);
            }

            $batch = $buffer->finalize();
            foreach ($batch->records() as $record) {
                if ($record['type'] === 'json') {
                    $payload = $record['payload'];
                    if (
                        !\is_array($payload)
                        || \array_keys($payload) !== [
                            'cli_sapi',
                            'json_available',
                            'pcre_available',
                            'php_supported',
                            'ready',
                        ]
                        || \count(\array_filter($payload, '\is_bool')) !== 5
                    ) {
                        throw new \UnexpectedValueException('cli-doctor-record-invalid');
                    }
                } elseif ($record['type'] !== 'error') {
                    throw new \UnexpectedValueException('cli-doctor-record-invalid');
                }
            }

            $writer->write(new PlainFormatter(new AnsiDecorator())->format($batch, false));

            return $exitCode;
        } catch (\Throwable) {
            try {
                $writer->write(new FormattedOutput('', 'CORETSIA_CLI_RENDER_FAILURE: render-failure'));
            } catch (\Throwable) {
                // No alternate output sink is allowed after a writer failure.
            }

            return 1;
        }
    }
}
