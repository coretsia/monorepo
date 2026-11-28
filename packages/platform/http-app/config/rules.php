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
 * Validation rules for the `http_app` configuration subtree.
 *
 * These rules intentionally keep the HttpApp config strict:
 * - the file validates the `http_app` root subtree;
 * - every declared key is required, including disabled feature settings;
 * - unknown keys are rejected at every declared map level;
 * - reserved `@*` keys are rejected by the same strict-shape policy;
 * - `http_app.handlers` must be a list of closed maps containing exactly
 *   `action_id` and `service_id`, both strings;
 * - `http_app.route_middleware` must be a list of non-empty, whitespace-free
 *   service id strings;
 * - `http_app.arguments.allow_container_types` must be a list of strings;
 * - all argument, response mapping, tracing, metrics, and logging toggles
 *   must be actual booleans;
 * - semantic handler registration, concrete-service allowlist, and route
 *   middleware admission validation remain provider-owned;
 * - module activation and route-specific middleware execution order are
 *   not configurable through this subtree.
 *
 * These rules contain only existing declarative ConfigValidator types and
 * constraints. They MUST NOT execute validators, resolve services, or
 * introduce another configuration-validation pipeline.
 *
 * The defaults file must return the subtree only:
 *
 *     config/http_app.php => ['handlers' => [], 'route_middleware' => [], ...]
 *
 * It must not return:
 *
 *     ['http_app' => [...]]
 */
return [
    'schemaVersion' => 1,
    'configRoot' => 'http_app',
    'additionalKeys' => false,
    'keys' => [
        'handlers' => [
            'required' => true,
            'type' => 'list',
            'items' => [
                'type' => 'map',
                'additionalKeys' => false,
                'keys' => [
                    'action_id' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                    'service_id' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                ],
            ],
        ],
        'route_middleware' => [
            'required' => true,
            'type' => 'list',
            'items' => [
                'type' => 'non-empty-string-no-ws',
            ],
        ],
        'arguments' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'allow_request' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'allow_route_params' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'allow_container_types' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
        'response' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'json' => [
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
                'html' => [
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
        ],
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
