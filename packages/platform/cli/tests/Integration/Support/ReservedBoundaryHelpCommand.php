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

/** External service whose constructor must never run during tag-based discovery. */
final class ReservedBoundaryHelpCommand implements CommandInterface
{
    public const string NAME = 'help';
    public const string SUMMARY = 'PRIVATE_EXTERNAL_HELP_METADATA';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public static int $constructed = 0;

    public function __construct()
    {
        ++self::$constructed;
        $sentinel = \getenv('CORETSIA_TEST_RESERVED_HELP_CONSTRUCTED');
        if (\is_string($sentinel) && $sentinel !== '') {
            \file_put_contents($sentinel, 'constructed');
        }
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        throw new \LogicException('Reserved external command must never execute.');
    }
}
