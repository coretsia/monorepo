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

namespace Coretsia\Tools\Spikes\_support;

/**
 * @internal
 *
 * Process runner abstraction used by DeterminismRunner to allow safe integration tests
 * without git/composer side effects.
 */
interface ProcessRunnerInterface
{
    public function run(
        string $command,
        string $cwd,
        ?array $env,
        bool   $captureStdout,
        bool   $captureStderr
    ): ProcessResult;

    /**
     * Run a command with stdout/stderr redirected to null device.
     *
     * Contract:
     * - Returns int exit code on successful spawn+wait.
     * - Returns null if process could not be started (proc_open failure).
     */
    public function runSilenced(string $command, string $cwd, array $env): ?int;
}
