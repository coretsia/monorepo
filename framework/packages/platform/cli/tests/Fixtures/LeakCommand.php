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

namespace Coretsia\Platform\Cli\Tests\Fixtures;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

/**
 * Fixture command for integration redaction test.
 *
 * Policy: MUST use OutputInterface only (no direct stdout/stderr).
 */
final class LeakCommand implements CommandInterface
{
    public function name(): string
    {
        return 'leak';
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $secret = 'superSecret';

        // Text: KEY=VALUE + Authorization header + absolute path.
        $output->text('API_TOKEN=' . $secret);
        $output->text('Authorization: Bearer ' . $secret);
        $output->text('path=C:\\OSPanel\\home\\coretsia-framework.local\\framework\\bin\\coretsia');

        // JSON: key-based redaction + absolute-path scrubbing in string values.
        $output->json([
            'apiToken' => $secret,
            'nested' => [
                'password' => $secret,
                'path' => 'C:\\OSPanel\\home\\coretsia-framework.local\\framework\\bin\\coretsia',
            ],
            'list' => [
                ['session' => $secret],
            ],
        ]);

        return 0;
    }
}
