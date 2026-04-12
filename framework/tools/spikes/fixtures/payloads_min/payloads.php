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
 * Payload fixtures (Epic 0.70.0):
 * - Mixed map/list payloads suitable for:
 *   - PayloadNormalizer deterministic ordering
 *   - StableJsonEncoder byte-stable encoding
 *   - FloatPolicy rejection (floats/NaN/INF/-INF)
 *
 * IMPORTANT:
 * - Fixtures MUST NOT contain objects/resources/callables.
 * - "ok_http_middleware_config" is derived from HTTP middleware config arrays (no floats).
 * - "forbidden_float_*" samples include floats (and NaN/INF/-INF) for FloatPolicy tests.
 *
 * @return array<string, array>
 */
return [
    /**
     * SHOULD: derived from HTTP middleware config arrays (no floats).
     * Contains nested maps/lists and intentionally unsorted map keys.
     */
    'ok_http_middleware_config' => [
        // Intentionally out-of-order top-level keys.
        'schema_version' => 1,
        'middleware' => [
            // Intentionally out-of-order map keys.
            'route' => [],
            'system_pre' => [
                'Coretsia\\Http\\Middleware\\CorrelationIdMiddleware',
                'Coretsia\\Http\\Middleware\\RequestIdMiddleware',
                'Coretsia\\Http\\Middleware\\TraceContextMiddleware',
                'Coretsia\\Http\\Middleware\\HttpMetricsMiddleware',
                'Coretsia\\Http\\Middleware\\AccessLogMiddleware',
                'Coretsia\\Http\\Maintenance\\MaintenanceMiddleware',
            ],
            'app' => [
                'Coretsia\\HttpApp\\Middleware\\RouterMiddleware',
            ],
            'system_post' => [
                'Coretsia\\Http\\Middleware\\CacheHeadersMiddleware',
                'Coretsia\\Http\\Middleware\\EtagMiddleware',
                'Coretsia\\Http\\Middleware\\CompressionMiddleware',
                'Coretsia\\Http\\Middleware\\SecurityHeadersMiddleware',
            ],
            'app_pre' => [
                'Coretsia\\Tenancy\\Http\\Middleware\\TenantContextMiddleware',
                'Coretsia\\Session\\Http\\Middleware\\SessionMiddleware',
                'Coretsia\\Auth\\Http\\Middleware\\AuthMiddleware',
                'Coretsia\\Security\\Http\\Middleware\\CsrfMiddleware',
            ],
            'system' => [],
            'app_post' => [],
            'route_pre' => [],
            'route_post' => [],
        ],
        'opt_in' => [
            'platform/auth' => [
                'Coretsia\\Auth\\Http\\Middleware\\RequireAuthMiddleware',
                'Coretsia\\Auth\\Http\\Middleware\\RequireAbilityMiddleware',
            ],
        ],
        'meta' => [
            // Nested map with intentionally unsorted keys.
            'notes' => 'fixture-derived-from-http-middleware-config',
            'owner' => 'framework/tools/spikes/fixtures/payloads_min',
            'features' => [
                // Nested list of maps (stress normalization + stable encoding).
                [
                    'name' => 'slot-taxonomy',
                    'enabled' => true,
                    'tags' => ['system', 'app', 'route'],
                ],
                [
                    'name' => 'opt-in-middleware',
                    'enabled' => true,
                    'tags' => ['auth'],
                ],
                [
                    'name' => 'determinism',
                    'enabled' => true,
                    'tags' => ['sort-maps', 'preserve-lists'],
                ],
            ],
        ],
    ],

    /**
     * Large nested mixed map/list payload (no floats) to stress normalization + stable encoding.
     */
    'ok_large_nested_mixed' => [
        'z' => 'last',
        'a' => 'first',
        'tree' => [
            // Map with intentionally unsorted keys.
            'node2' => [
                'id' => 'n2',
                'children' => [
                    [
                        'id' => 'n2.c1',
                        'attrs' => [
                            'enabled' => true,
                            'priority' => 2,
                            'tags' => ['alpha', 'beta', 'gamma'],
                        ],
                    ],
                    [
                        'id' => 'n2.c2',
                        'attrs' => [
                            'enabled' => false,
                            'priority' => 3,
                            'tags' => ['delta', 'epsilon'],
                        ],
                    ],
                ],
            ],
            'node1' => [
                'id' => 'n1',
                'children' => [
                    [
                        'id' => 'n1.c1',
                        'attrs' => [
                            'enabled' => true,
                            'priority' => 1,
                            'tags' => ['one', 'two', 'three'],
                        ],
                    ],
                    [
                        'id' => 'n1.c2',
                        'attrs' => [
                            'enabled' => true,
                            'priority' => 0,
                            'tags' => [],
                        ],
                    ],
                ],
            ],
            'node3' => [
                'id' => 'n3',
                'children' => [],
            ],
        ],
        'matrix' => [
            // List-of-lists; list order must be preserved.
            [1, 2, 3, 4, 5],
            [5, 4, 3, 2, 1],
            [2, 3, 5, 7, 11],
        ],
        'empty' => [
            // Cemented: empty array is list.
            'as_list' => [],
        ],
        'flags' => [
            'debug' => true,
            'dry_run' => false,
            'nullish' => null,
        ],
        'strings' => [
            'ascii' => 'hello',
            'unicode' => 'привіт',
            'slashes' => 'a/b/c',
        ],
    ],

    /**
     * Forbidden: float value at nested path.
     * Expected FloatPolicy error code: CORETSIA_JSON_FLOAT_FORBIDDEN (path should point to "a.b[3].c").
     */
    'forbidden_float_nested' => [
        'a' => [
            'b' => [
                1,
                2,
                3,
                [
                    'c' => 1.25,
                ],
            ],
        ],
    ],

    /**
     * Forbidden: NaN (explicit).
     * Expected FloatPolicy error code: CORETSIA_JSON_FLOAT_FORBIDDEN
     */
    'forbidden_float_nan' => [
        'meta' => [
            'value' => NAN,
        ],
    ],

    /**
     * Forbidden: INF (explicit).
     * Expected FloatPolicy error code: CORETSIA_JSON_FLOAT_FORBIDDEN
     */
    'forbidden_float_inf' => [
        'meta' => [
            'value' => INF,
        ],
    ],

    /**
     * Forbidden: -INF (explicit).
     * Expected FloatPolicy error code: CORETSIA_JSON_FLOAT_FORBIDDEN
     */
    'forbidden_float_ninf' => [
        'meta' => [
            'value' => -INF,
        ],
    ],
];
