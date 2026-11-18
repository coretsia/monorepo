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
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;

/**
 * Disables application-local maintenance mode.
 *
 * The shared maintenance state store owns idempotent flag removal and
 * retained-message handling. Success is reported only after it completes.
 */
final readonly class MaintenanceDisableCommand implements CommandInterface
{
    public const string NAME = 'maintenance:disable';
    public const string SUMMARY = 'Disable maintenance mode.';
    public const string GROUP = 'maintenance';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    private const string ERROR_CODE_INVALID = 'CORETSIA_MAINTENANCE_COMMAND_INVALID';

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
            || $input->options() !== []
        ) {
            $output->error(self::ERROR_CODE_INVALID, 'http-maintenance-command-invalid');
            return 1;
        }

        try {
            $this->stateStore->disable();
        } catch (MaintenanceStateUnavailableException $exception) {
            $output->error($exception->errorCode(), $exception->reason());
            return 1;
        }

        $output->json(['enabled' => false]);
        return 0;
    }
}
