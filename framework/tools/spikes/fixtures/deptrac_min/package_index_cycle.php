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
 * Deptrac fixtures: package index (cycle scenario).
 *
 * Cycle:
 *   demo/pkg-a -> demo/pkg-b -> demo/pkg-a
 *
 * @return array{
 *   schema_version:int,
 *   repo_root:string,
 *   packages: array<string, array{
 *     package_id:string,
 *     composer:string,
 *     path:string,
 *     module_id:string,
 *     deps:list<string>,
 *     allowlist:list<string>
 *   }>
 * }
 */
return [
    'schema_version' => 1,
    'repo_root' => 'repo',

    'packages' => [
        'demo/pkg-a' => [
            'package_id' => 'demo/pkg-a',
            'composer' => 'coretsia/demo-pkg-a',
            'path' => 'packages/demo/pkg-a',
            'module_id' => 'demo.pkg_a',
            'deps' => [
                'demo/pkg-b',
            ],
            'allowlist' => [
                'tests/**',
            ],
        ],

        'demo/pkg-b' => [
            'package_id' => 'demo/pkg-b',
            'composer' => 'coretsia/demo-pkg-b',
            'path' => 'packages/demo/pkg-b',
            'module_id' => 'demo.pkg_b',
            'deps' => [
                'demo/pkg-a',
            ],
            'allowlist' => [
                'tests/**',
            ],
        ],
    ],
];
