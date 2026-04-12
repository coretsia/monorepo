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
 * Deptrac fixtures: package index (OK / no cycles).
 *
 * Purpose:
 * - input for DeptracGenerate prototype (Epic 0.80.0)
 * - includes >=2 packages
 * - includes allowlist scenario: tests/** only (policy-valid)
 *
 * Notes:
 * - Intentionally uses some out-of-order keys to ensure generator normalizes deterministically.
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

    // Relative (fixture-local) repo root to avoid absolute paths anywhere.
    'repo_root' => 'repo',

    'packages' => [
        // Intentionally out-of-order: pkg-b first.
        'demo/pkg-b' => [
            'package_id' => 'demo/pkg-b',
            'composer' => 'coretsia/demo-pkg-b',
            'path' => 'packages/demo/pkg-b',
            'module_id' => 'demo.pkg_b',

            // No deps.
            'deps' => [],

            // Allowlist policy-valid: tests/** only.
            'allowlist' => [
                'tests/**',
            ],
        ],

        'demo/pkg-a' => [
            'package_id' => 'demo/pkg-a',
            'composer' => 'coretsia/demo-pkg-a',
            'path' => 'packages/demo/pkg-a',
            'module_id' => 'demo.pkg_a',

            // A depends on B (no cycle).
            'deps' => [
                'demo/pkg-b',
            ],

            // Allowlist policy-valid: tests/** only.
            'allowlist' => [
                'tests/**',
            ],
        ],
    ],
];
