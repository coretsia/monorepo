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

namespace Coretsia\Platform\Cli\Tests\Fake;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

final class FakeWorkspaceSyncApplyCommand implements CommandInterface
{
    public static int $calls = 0;

    public static function reset(): void
    {
        self::$calls = 0;
    }

    public function name(): string
    {
        return 'workspace:sync --apply';
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        self::$calls++;

        return 0;
    }
}
