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

namespace Coretsia\Contracts\Cli\Command;

use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

/**
 * CLI command port used by CLI implementations (e.g. coretsia/cli).
 */
interface CommandInterface
{
    /**
     * Canonical command name (stable identifier used for routing/registry).
     */
    public function name(): string;

    /**
     * Execute the command.
     *
     * @return int Process exit code (0 = success; non-zero = failure)
     */
    public function run(InputInterface $input, OutputInterface $output): int;
}
