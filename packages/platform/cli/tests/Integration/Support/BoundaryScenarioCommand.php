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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

/** Controllable external command; no production code depends on this fixture. */
final class BoundaryScenarioCommand implements CommandInterface
{
    public const string NAME = 'external:boundary';
    public const string SUMMARY = 'External command used to verify dispatch boundaries.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public static int $exitCode = 0;
    public static bool $throw = false;
    public static bool $identityMismatch = false;
    public static bool $constructed = false;
    public static bool $ran = false;
    public static bool $constructedInUow = false;
    public static bool $ranInUow = false;
    public static ?BoundaryScenarioRuntime $runtime = null;

    public function __construct()
    {
        self::$constructed = true;
        self::$constructedInUow = self::$runtime?->active ?? false;
        if (self::$runtime !== null) {
            self::$runtime->events[] = 'construct';
        }
    }

    public static function reset(BoundaryScenarioRuntime $runtime, int $code = 0, bool $throw = false): void
    {
        self::$runtime = $runtime;
        self::$exitCode = $code;
        self::$throw = $throw;
        self::$identityMismatch = false;
        self::$constructed = false;
        self::$ran = false;
        self::$constructedInUow = false;
        self::$ranInUow = false;
    }

    public function name(): string
    {
        return self::$identityMismatch ? 'external:wrong-name' : self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        self::$ran = true;
        self::$ranInUow = self::$runtime?->active ?? false;
        if (self::$runtime !== null) {
            self::$runtime->events[] = 'run';
        }
        $output->json(['status' => 'owner-handled']);
        if (self::$throw) {
            throw new \RuntimeException('PRIVATE_OWNER_THROWABLE');
        }
        return self::$exitCode;
    }
}
