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

// IMPORTANT (SSoT shape rule):
// This file MUST return the `cli` subtree (NO repeated root key like ['cli' => ...]).
// It registers devtools-only commands into `cli.commands` when the preset is loaded.

return [
    'commands' => [
        \Coretsia\Devtools\CliSpikes\Command\DoctorCommand::class,
        \Coretsia\Devtools\CliSpikes\Command\SpikeFingerprintCommand::class,
        \Coretsia\Devtools\CliSpikes\Command\SpikeConfigDebugCommand::class,
        \Coretsia\Devtools\CliSpikes\Command\DeptracGraphCommand::class,
        \Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncDryRunCommand::class,
        \Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncApplyCommand::class,
    ],
];
