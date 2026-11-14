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
 * Validation rules for the `http` configuration subtree.
 *
 * These rules intentionally keep the HTTP config strict:
 * - the file validates the `http` root subtree;
 * - every declared key is required, including disabled feature settings;
 * - unknown keys are rejected at every declared map level;
 * - reserved `@*` keys are rejected by the same strict-shape policy;
 * - `http.factory` must be exactly `external`, `nyholm`, `guzzlehttp`,
 *   `laminas`, or `slim`; `auto` and differently cased values are invalid;
 * - `http.middleware` contains exactly the nine canonical slot append lists;
 * - each middleware slot must be a list of non-empty, whitespace-free service
 *   id strings; class-string grammar and cross-slot duplicate checks remain
 *   owned by HttpMiddlewareStackBuilder;
 * - correlation, tracing, metrics, access logging, proxy, context enrichment,
 *   host validation, and JSON parsing toggles must be actual booleans;
 * - `http.correlation.header_name` must be non-empty and whitespace-free;
 * - `http.proxy.trusted_proxies` and `http.hosts.allowed` must be lists of
 *   non-empty, whitespace-free strings;
 * - HTTP-owned policies validate canonical header tokens, IP/CIDR addresses,
 *   host syntax, entry lengths, and bounded list limits; ConfigValidator
 *   supplies only the existing declarative structural/string type rules;
 * - `http.request.max_body_bytes` must be an integer in 1..16777216;
 * - `http.request.json.max_depth` must be an integer in 1..512;
 * - no `http.middleware.auto.*`, implicit factory selection, implementation
 *   class-name, Composer installation, optional module activation, route,
 *   endpoint, or environment-specific disclosure keys are declared.
 *
 * These rules contain only existing declarative ConfigValidator types and
 * constraints. They MUST NOT inspect installed PSR-7/17 packages, resolve
 * factory services, execute validators, or introduce another rules DSL.
 * Effective PSR-17 binding compatibility is checked at HTTP runtime readiness.
 *
 * The defaults file must return the subtree only:
 *
 *     config/http.php => ['factory' => 'external', 'middleware' => [...], ...]
 *
 * It must not return:
 *
 *     ['http' => [...]]
 */
return [
    'schemaVersion' => 1,
    'configRoot' => 'http',
    'additionalKeys' => false,
    'keys' => [
        'factory' => [
            'required' => true,
            'type' => 'string',
            'allowedValues' => [
                'external',
                'nyholm',
                'guzzlehttp',
                'laminas',
                'slim',
            ],
        ],
        'middleware' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'system_pre' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'system' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'system_post' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'app_pre' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'app' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'app_post' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'route_pre' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'route' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'route_post' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
            ],
        ],
        'correlation' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'header_name' => [
                    'required' => true,
                    'type' => 'non-empty-string-no-ws',
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
        'access_log' => [
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
        'proxy' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'trusted_proxies' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
            ],
        ],
        'context' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enrich' => [
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
        'hosts' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'allowed' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
            ],
        ],
        'request' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'max_body_bytes' => [
                    'required' => true,
                    'type' => 'int',
                    'min' => 1,
                    'max' => 16777216,
                ],
                'json' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'enabled' => [
                            'required' => true,
                            'type' => 'bool',
                        ],
                        'max_depth' => [
                            'required' => true,
                            'type' => 'int',
                            'min' => 1,
                            'max' => 512,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
