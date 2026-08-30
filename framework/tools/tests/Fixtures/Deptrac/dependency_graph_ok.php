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
 * Canonical synthetic dependency graph fixture for deptrac tooling tests.
 *
 * @return array{
 *     packages: array<string, array{
 *         composer: non-empty-string,
 *         deps: list<non-empty-string>
 *     }>
 * }
 */
return [
    'packages' => [
        // Intentionally out of order: generator must normalize deterministically.
        'demo/pkg-b' => [
            'composer' => 'coretsia/demo-pkg-b',
            'deps' => [],
        ],
        'demo/pkg-a' => [
            'composer' => 'coretsia/demo-pkg-a',
            'deps' => [
                'demo/pkg-b',
            ],
        ],
    ],
];
