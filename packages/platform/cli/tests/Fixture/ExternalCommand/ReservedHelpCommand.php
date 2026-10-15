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

namespace Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

/**
 * External command identity that deliberately collides with the built-in help.
 */
final readonly class ReservedHelpCommand implements CommandInterface
{
    public const string NAME = 'help';
    public const string SUMMARY = 'External reserved-name collision fixture.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}
