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

/**
 * Single Source of Truth for structure generator ignore lists.
 *
 * @return array{ignoreDirs:list<string>, ignoreFiles:list<string>}
 */
return [
    'ignoreDirs' => [
        '.git',
        '.idea',
        '.osp',
        '.phpstan-cache',
        'phpstan',
        '.phpunit.cache',
        'phpunit-cache',
        'phpunit',
        'vendor',
    ],
    'ignoreFiles' => [
        '.DS_Store',
        'preload.php',
        '.deptrac.cache',
    ],
];
