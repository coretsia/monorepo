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

namespace Coretsia\Platform\Http\Console;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Platform\Http\Exception\MaintenanceStateUnavailableException;
use Coretsia\Platform\Http\Maintenance\MaintenanceMessage;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;

/**
 * Enables application-local maintenance mode.
 *
 * Validates parsed command input and the optional public message before
 * delegating state mutation to the shared maintenance state store.
 * Diagnostics and success output never expose supplied values or paths.
 */
final readonly class MaintenanceEnableCommand implements CommandInterface
{
    public const string NAME = 'maintenance:enable';
    public const string SUMMARY = 'Enable maintenance mode.';
    public const string GROUP = 'maintenance';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [
        ['name' => 'message', 'summary' => 'Public maintenance message.', 'value' => 'required', 'repeatable' => false],
        ['name' => 'retry-after', 'summary' => 'Retry delay in seconds.', 'value' => 'required', 'repeatable' => false],
    ];

    private const string ERROR_CODE_INVALID = 'CORETSIA_MAINTENANCE_COMMAND_INVALID';
    private const string ERROR_CODE_MESSAGE_INVALID = 'CORETSIA_MAINTENANCE_MESSAGE_INVALID';

    public function __construct(
        private MaintenanceStateStore $stateStore,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        if (
            $input->commandName() !== self::NAME
            || $input->arguments() !== []
            || \array_diff_key($input->options(), ['message' => true, 'retry-after' => true]) !== []
        ) {
            $output->error(self::ERROR_CODE_INVALID, 'http-maintenance-command-invalid');
            return 1;
        }

        $message = null;
        if ($input->hasOption('message')) {
            $message = $input->option('message');
            if (!\is_string($message)) {
                $output->error(self::ERROR_CODE_INVALID, 'http-maintenance-command-invalid');
                return 1;
            }
        }

        $retryAfterSeconds = null;
        if ($input->hasOption('retry-after')) {
            $retryAfter = $input->option('retry-after');
            if ($message === null || !\is_string($retryAfter)) {
                $output->error(self::ERROR_CODE_INVALID, 'http-maintenance-command-invalid');
                return 1;
            }
            if (
                \preg_match('/\A[1-9][0-9]{0,4}\z/', $retryAfter) !== 1
                || (\strlen($retryAfter) === 5 && \strcmp($retryAfter, '86400') > 0)
            ) {
                $output->error(self::ERROR_CODE_MESSAGE_INVALID, 'http-maintenance-message-invalid');
                return 1;
            }
            $retryAfterSeconds = (int) $retryAfter;
        }

        $maintenanceMessage = null;
        if ($message !== null) {
            try {
                $maintenanceMessage = new MaintenanceMessage(
                    schemaVersion: 1,
                    message: $message,
                    retryAfterSeconds: $retryAfterSeconds,
                );
            } catch (\InvalidArgumentException) {
                $output->error(self::ERROR_CODE_MESSAGE_INVALID, 'http-maintenance-message-invalid');
                return 1;
            }
        }

        try {
            $this->stateStore->enable($maintenanceMessage);
        } catch (MaintenanceStateUnavailableException $exception) {
            $output->error($exception->errorCode(), $exception->reason());
            return 1;
        }

        $output->json(['enabled' => true]);
        return 0;
    }
}
