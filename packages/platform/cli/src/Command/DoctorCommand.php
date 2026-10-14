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

namespace Coretsia\Platform\Cli\Command;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

/**
 * Dependency-free capability checks for the ultra-early doctor entrypoint.
 *
 * @internal
 */
final class DoctorCommand implements CommandInterface
{
    public const string NAME = 'doctor';
    public const string SUMMARY = 'Check CLI bootstrap and runtime prerequisites.';
    public const string GROUP = 'core';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $phpSupported = \PHP_VERSION_ID >= 80400;
        $cliSapi = \PHP_SAPI === 'cli';
        $jsonAvailable = \extension_loaded('json');
        $pcreAvailable = \extension_loaded('pcre');
        $ready = $phpSupported && $cliSapi && $jsonAvailable && $pcreAvailable;

        // Fixed capability names and boolean results only; no host metadata.
        $output->json([
            'cli_sapi' => $cliSapi,
            'json_available' => $jsonAvailable,
            'pcre_available' => $pcreAvailable,
            'php_supported' => $phpSupported,
            'ready' => $ready,
        ]);

        return $ready ? 0 : 1;
    }
}
