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
 * Validation rules for the `routing` configuration subtree.
 *
 * These rules intentionally keep the Routing config strict:
 * - the file validates the `routing` root subtree;
 * - unknown keys are rejected at every declared map level;
 * - reserved `@*` keys are rejected by the same strict-shape policy;
 * - `routing.tracing` is a required map containing only `enabled`;
 * - `routing.metrics` is a required map containing only `enabled`;
 * - `routing.logging` is a required map containing only `enabled`;
 * - all three `enabled` values are required booleans;
 * - observability settings MUST NOT alter route matching or artifact validation;
 * - module activation, route compilation, and artifact readiness are not
 *   configurable through this subtree;
 * - reserved-route protection cannot be disabled;
 * - arbitrary artifact paths and runtime source fallback are not supported.
 *
 * The defaults file must return the subtree only:
 *
 *     config/routing.php => ['tracing' => [...], 'metrics' => [...], 'logging' => [...]]
 *
 * It must not return:
 *
 *     ['routing' => [...]]
 */
return [
    'schemaVersion' => 1,
    'configRoot' => 'routing',
    'additionalKeys' => false,
    'keys' => [
        'tracing' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
            ],
        ],
        'metrics' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
            ],
        ],
        'logging' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
            ],
        ],
    ],
];
