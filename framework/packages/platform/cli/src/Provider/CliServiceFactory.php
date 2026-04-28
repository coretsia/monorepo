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

namespace Coretsia\Platform\Cli\Provider;

use Coretsia\Platform\Cli\Application;
use Coretsia\Platform\Cli\Input\CliInput;
use Coretsia\Platform\Cli\Output\CliOutput;

/**
 * Stateless factory/wiring helper for CLI services.
 *
 * Invariants:
 * - MUST NOT keep mutable runtime state (no caches/buffers/singletons).
 * - MUST be deterministic for the same inputs.
 *
 * @internal
 */
final class CliServiceFactory
{
    private function __construct()
    {
        // static-only
    }

    public static function application(string $launcherFile): Application
    {
        return new Application($launcherFile);
    }

    public static function input(?array $argv = null): CliInput
    {
        return CliInput::fromArgv($argv);
    }

    /**
     * Build output using the merged `cli` subtree.
     *
     * Policy (Phase 0):
     * - cli.output.redaction.enabled defaults to true
     *
     * @param array<string, mixed> $cliSubtree
     * @param \Closure(string): void|null $stdoutWriter
     * @param \Closure(string): void|null $stderrWriter
     */
    public static function outputFromCliSubtree(
        array     $cliSubtree,
        ?\Closure $stdoutWriter = null,
        ?\Closure $stderrWriter = null,
    ): CliOutput {
        $enabled = $cliSubtree['output']['redaction']['enabled'] ?? true;
        $redactionEnabled = \is_bool($enabled) ? $enabled : true;

        return new CliOutput($redactionEnabled, $stdoutWriter, $stderrWriter);
    }
}
