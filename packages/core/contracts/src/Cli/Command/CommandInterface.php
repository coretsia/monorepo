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
 * CLI command execution contract.
 *
 * This is the only command execution contract that package-contributed commands
 * should implement. Platform-specific CLI implementations may discover,
 * register, adapt, and dispatch commands, but package commands must remain
 * coupled only to this contracts-level port.
 *
 * Contract invariants:
 *
 * - this contract MUST remain independent from platform/cli;
 * - package-contributed commands MUST NOT depend on platform/cli command
 *   implementation classes;
 * - command names returned by name() MUST be stable and deterministic;
 * - command names SHOULD match COMMAND_NAME_PATTERN;
 * - command classes SHOULD expose command identity constants:
 *   - public const string NAME;
 *   - public const string SUMMARY;
 *   - public const string GROUP;
 * - name() MUST return the same value as the command class NAME constant;
 * - provider tag metadata `name` MUST match CommandInterface::name();
 * - provider tag metadata SHOULD reference command class constants instead of
 *   unrelated string literals.
 *
 * Example command identity shape:
 *
 *     public const string NAME = 'worker:start';
 *     public const string SUMMARY = 'Start worker runtime.';
 *     public const string GROUP = 'worker';
 *
 *     public function name(): string
 *     {
 *         return self::NAME;
 *     }
 */
interface CommandInterface
{
    /**
     * Canonical CLI command name pattern.
     *
     * Examples:
     *
     * - worker:start
     * - worker:status
     * - cache:clear
     */
    public const string COMMAND_NAME_PATTERN = '\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z';

    /**
     * Returns the canonical command name.
     *
     * The returned value MUST be stable, deterministic, and suitable for
     * command registry lookup, provider tag metadata, diagnostics, and help
     * output.
     */
    public function name(): string;

    /**
     * Executes the command.
     *
     * Implementations receive already-parsed CLI input and a contracts-level
     * output port. They MUST NOT depend on platform/cli implementation classes.
     *
     * @return int Process exit code. Zero means success; non-zero means failure.
     */
    public function run(InputInterface $input, OutputInterface $output): int;
}
