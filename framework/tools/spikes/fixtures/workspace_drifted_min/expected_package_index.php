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

return [
    [
        'slug' => 'contracts',
        'layer' => 'core',
        'path' => 'packages/core/contracts',
        'composerName' => 'coretsia/core-contracts',
        'psr4' => [
            'Coretsia\\Core\\Contracts\\' => 'src/',
        ],
        'kind' => 'library',
    ],
    [
        'slug' => 'kernel',
        'layer' => 'core',
        'path' => 'packages/core/kernel',
        'composerName' => 'coretsia/core-kernel',
        'psr4' => [
            'Coretsia\\Core\\Kernel\\' => 'src/',
        ],
        'kind' => 'runtime',
        'moduleClass' => 'Coretsia\\Core\\Kernel\\Module\\KernelModule',
    ],
    [
        'slug' => 'internal-toolkit',
        'layer' => 'devtools',
        'path' => 'packages/devtools/internal-toolkit',
        'composerName' => 'coretsia/devtools-internal-toolkit',
        'psr4' => [
            'Coretsia\\Devtools\\InternalToolkit\\' => 'src/',
        ],
        'kind' => 'library',
    ],
    [
        'slug' => 'cli',
        'layer' => 'platform',
        'path' => 'packages/platform/cli',
        'composerName' => 'coretsia/platform-cli',
        'psr4' => [
            'Coretsia\\Platform\\Cli\\' => 'src/',
        ],
        'kind' => 'runtime',
        'moduleClass' => 'Coretsia\\Platform\\Cli\\Module\\CliModule',
    ],
];
