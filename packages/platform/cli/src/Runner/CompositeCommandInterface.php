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

namespace Coretsia\Platform\Cli\Runner;

use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

/**
 * Internal execution contract for the built-in workflow:run composite command.
 *
 * Only the platform-owned WorkflowRunCommand may implement this interface.
 * It is not a command-owner extension point and MUST NOT be combined with
 * Coretsia\Contracts\Cli\Command\CommandInterface on one service.
 *
 * Composite execution has no outer Kernel CLI UoW. Each workflow step uses
 * the normal CommandRunner path and receives its own canonical CLI UoW.
 * Implementations MUST NOT start a UoW directly, render final output, or
 * write to stdout or stderr.
 *
 * @internal
 */
interface CompositeCommandInterface
{
    /**
     * Returns the exact canonical command identity declared by the
     * implementing class's public NAME constant.
     */
    public function name(): string;

    /**
     * Executes a composite command with validated invocation input and an
     * invocation-local output port.
     *
     * The effective output format is exactly one of json, table, or plain.
     * The CommandRunner validates the returned portable process exit code.
     *
     * @return int Process exit code in the range 0..255.
     */
    public function run(
        InputInterface $input,
        OutputInterface $output,
        string $outputFormat,
    ): int;
}
