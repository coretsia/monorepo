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
 * Scenarios matrix (normative for this fixture):
 *  - Precedence invariant: app > module > skeleton > defaults.
 *  - Keys for middleware slots are cemented (must exist in at least baseline scenarios).
 *  - Directive nodes are represented as a map with exactly one directive key at that level:
 *      ['@append' => [...]] | ['@prepend' => [...]] | ['@remove' => [...]] | ['@merge' => [...]] | ['@replace' => mixed]
 *  - Reserved namespace guard: any '@*' key not in allowlist must fail deterministically with
 *    CORETSIA_CONFIG_RESERVED_NAMESPACE_USED (highest precedence among directive errors).
 *
 * Structure:
 *  - schema_version: int
 *  - precedence: list<string> (lowest -> highest)
 *  - scenarios: array<string, array{
 *        title: string,
 *        inputs: array{defaults: array, skeleton: array, module: array, app: array},
 *        expect: array{merged?: array, error_code?: string},
 *        notes?: string
 *    }>
 */
return [
    'schema_version' => 1,

    // Lowest -> highest precedence.
    'precedence' => [
        'defaults',
        'skeleton',
        'module',
        'app',
    ],

    'scenarios' => [
        // ---------------------------------------------------------------------
        // Baselines / precedence matrix
        // ---------------------------------------------------------------------

        'baseline.defaults_only.all_middleware_slots_present' => [
            'title' => 'Defaults only: all middleware slot keys + auto bool are present',
            'inputs' => [
                'defaults' => [
                    'http.middleware.auto' => true,

                    'http.middleware.system_pre' => [
                        'CorrelationId',
                        'RequestId',
                        'TraceContext',
                        'HttpMetrics',
                        'AccessLog',
                        'Maintenance',
                    ],
                    'http.middleware.system' => [],
                    'http.middleware.system_post' => [
                        'CacheHeaders',
                        'Etag',
                        'Compression',
                    ],

                    'http.middleware.app_pre' => [
                        'TenantContext',
                        'Session',
                        'Auth',
                    ],
                    'http.middleware.app' => [
                        'Router',
                    ],
                    'http.middleware.app_post' => [],

                    'http.middleware.route_pre' => [],
                    'http.middleware.route' => [],
                    'http.middleware.route_post' => [],
                ],
                'skeleton' => [],
                'module' => [],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.app' => [
                        'Router',
                    ],
                    'http.middleware.app_post' => [],
                    'http.middleware.app_pre' => [
                        'TenantContext',
                        'Session',
                        'Auth',
                    ],
                    'http.middleware.auto' => true,
                    'http.middleware.route' => [],
                    'http.middleware.route_post' => [],
                    'http.middleware.route_pre' => [],
                    'http.middleware.system' => [],
                    'http.middleware.system_post' => [
                        'CacheHeaders',
                        'Etag',
                        'Compression',
                    ],
                    'http.middleware.system_pre' => [
                        'CorrelationId',
                        'RequestId',
                        'TraceContext',
                        'HttpMetrics',
                        'AccessLog',
                        'Maintenance',
                    ],
                ],
            ],
        ],

        'precedence.skeleton_over_defaults.list_replace' => [
            'title' => 'Skeleton overrides defaults: list value is replaced (no implicit list merge)',
            'inputs' => [
                'defaults' => [
                    'http.middleware.system_post' => [
                        'CacheHeaders',
                        'Etag',
                        'Compression',
                    ],
                ],
                'skeleton' => [
                    'http.middleware.system_post' => [
                        'CacheHeaders',
                        'Etag',
                    ],
                ],
                'module' => [],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.system_post' => [
                        'CacheHeaders',
                        'Etag',
                    ],
                ],
            ],
        ],

        'precedence.module_over_skeleton.scalar_replace' => [
            'title' => 'Module overrides skeleton: scalar/bool is replaced',
            'inputs' => [
                'defaults' => [
                    'http.middleware.auto' => true,
                ],
                'skeleton' => [
                    'http.middleware.auto' => true,
                ],
                'module' => [
                    'http.middleware.auto' => false,
                ],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.auto' => false,
                ],
            ],
        ],

        'precedence.app_over_module_and_skeleton.multi_key' => [
            'title' => 'App wins over module/skeleton across multiple keys',
            'inputs' => [
                'defaults' => [
                    'http.middleware.auto' => true,
                    'http.middleware.app' => ['Router'],
                ],
                'skeleton' => [
                    'http.middleware.auto' => true,
                    'http.middleware.app' => ['Router', 'SkeletonExtra'],
                ],
                'module' => [
                    'http.middleware.auto' => false,
                    'http.middleware.app' => ['Router', 'ModuleExtra'],
                ],
                'app' => [
                    'http.middleware.auto' => true,
                    'http.middleware.app' => ['Router', 'AppExtra'],
                ],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.app' => ['Router', 'AppExtra'],
                    'http.middleware.auto' => true,
                ],
            ],
        ],

        // ---------------------------------------------------------------------
        // Directive success cases (cemented examples)
        // ---------------------------------------------------------------------

        'directive.append.debugbar_into_system_post' => [
            'title' => '@append adds Debugbar middleware into a slot list (system_post)',
            'inputs' => [
                'defaults' => [
                    'http.middleware.system_post' => [
                        'CacheHeaders',
                        'Etag',
                        'Compression',
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system_post' => [
                        '@append' => [
                            'Debugbar',
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.system_post' => [
                        'CacheHeaders',
                        'Etag',
                        'Compression',
                        'Debugbar',
                    ],
                ],
            ],
        ],

        'directive.remove.maintenance_from_system_pre' => [
            'title' => '@remove removes Maintenance middleware from a slot list (system_pre)',
            'inputs' => [
                'defaults' => [
                    'http.middleware.system_pre' => [
                        'CorrelationId',
                        'RequestId',
                        'Maintenance',
                        'TraceContext',
                    ],
                ],
                'skeleton' => [],
                'module' => [],
                'app' => [
                    'http.middleware.system_pre' => [
                        '@remove' => [
                            'Maintenance',
                        ],
                    ],
                ],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.system_pre' => [
                        'CorrelationId',
                        'RequestId',
                        'TraceContext',
                    ],
                ],
            ],
        ],

        'directive.prepend.errorhandling_into_system_pre' => [
            'title' => '@prepend adds ErrorHandling middleware at the beginning of a slot list (system_pre)',
            'inputs' => [
                'defaults' => [
                    'http.middleware.system_pre' => [
                        'CorrelationId',
                        'RequestId',
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system_pre' => [
                        '@prepend' => [
                            'ErrorHandling',
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.system_pre' => [
                        'ErrorHandling',
                        'CorrelationId',
                        'RequestId',
                    ],
                ],
            ],
        ],

        'directive.replace.auto_false.scalar' => [
            'title' => '@replace sets http.middleware.auto => false (scalar replace scenario)',
            'inputs' => [
                'defaults' => [
                    'http.middleware.auto' => true,
                ],
                'skeleton' => [],
                'module' => [],
                'app' => [
                    'http.middleware.auto' => [
                        '@replace' => false,
                    ],
                ],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.auto' => false,
                ],
            ],
        ],

        // ---------------------------------------------------------------------
        // Directive semantics: missing base + @merge + recursion
        // ---------------------------------------------------------------------

        'directive.append.missing_base_treated_as_empty_list' => [
            'title' => 'Missing-base semantics: @append treats missing base as empty list []',
            'inputs' => [
                'defaults' => [
                    // Intentionally missing: http.middleware.route
                ],
                'skeleton' => [],
                'module' => [
                    'http.middleware.route' => [
                        '@append' => [
                            'RouteGuard',
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.route' => [
                        'RouteGuard',
                    ],
                ],
            ],
        ],

        'directive.remove.idempotent.strict_equality' => [
            'title' => 'Removal semantics: @remove is idempotent and uses strict equality (===)',
            'inputs' => [
                'defaults' => [
                    'http.middleware.system' => [
                        'A',
                        'B',
                        'B',
                        'C',
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system' => [
                        '@remove' => [
                            'B',
                            'X',
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.system' => [
                        'A',
                        'C',
                    ],
                ],
            ],
        ],

        'directive.merge.recursive_map_merge_lists_replaced' => [
            'title' => '@merge: recursive map merge; lists are replaced unless list directives are used',
            'inputs' => [
                'defaults' => [
                    'http.headers' => [
                        'security' => [
                            'x_frame_options' => 'DENY',
                            'csp' => [
                                'enabled' => true,
                                'policy' => 'default-src \'self\'',
                            ],
                        ],
                        'cors' => [
                            'allowed_origins' => ['https://a.example'],
                        ],
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.headers' => [
                        '@merge' => [
                            'security' => [
                                'csp' => [
                                    'enabled' => false,
                                ],
                            ],
                            'cors' => [
                                // list is replaced here (no list merge)
                                'allowed_origins' => ['https://b.example'],
                            ],
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.headers' => [
                        'cors' => [
                            'allowed_origins' => ['https://b.example'],
                        ],
                        'security' => [
                            'csp' => [
                                'enabled' => false,
                                'policy' => 'default-src \'self\'',
                            ],
                            'x_frame_options' => 'DENY',
                        ],
                    ],
                ],
            ],
        ],

        'directive.merge.missing_base_treated_as_empty_map' => [
            'title' => 'Missing-base semantics: @merge treats missing base as empty map [] (empty-array rule applies)',
            'inputs' => [
                'defaults' => [
                    // Intentionally missing: http.features
                ],
                'skeleton' => [],
                'module' => [
                    'http.features' => [
                        '@merge' => [
                            'request_id' => true,
                            'trace' => true,
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.features' => [
                        'request_id' => true,
                        'trace' => true,
                    ],
                ],
            ],
        ],

        // ---------------------------------------------------------------------
        // Empty-array rule (cemented)
        // ---------------------------------------------------------------------

        'typing.empty_array.append_is_allowed_and_noop' => [
            'title' => 'Empty-array rule: @append with [] is accepted as empty list and is a no-op',
            'inputs' => [
                'defaults' => [
                    'http.middleware.system_post' => [
                        'CacheHeaders',
                        'Etag',
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system_post' => [
                        '@append' => [],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.system_post' => [
                        'CacheHeaders',
                        'Etag',
                    ],
                ],
            ],
        ],

        'typing.empty_array.merge_is_allowed_and_noop' => [
            'title' => 'Empty-array rule: @merge with [] is accepted as empty map and is a no-op',
            'inputs' => [
                'defaults' => [
                    'http.features' => [
                        'request_id' => true,
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.features' => [
                        '@merge' => [],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'merged' => [
                    'http.features' => [
                        'request_id' => true,
                    ],
                ],
            ],
        ],

        // ---------------------------------------------------------------------
        // Reserved namespace guard (cemented)
        // ---------------------------------------------------------------------

        'error.reserved_namespace.unknown_directive_root_must_fail' => [
            'title' => 'Reserved namespace guard: ["@foo" => "bar"] at root MUST fail',
            'inputs' => [
                'defaults' => [
                    'http.middleware.auto' => true,
                ],
                'skeleton' => [],
                'module' => [
                    '@foo' => 'bar',
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_RESERVED_NAMESPACE_USED',
            ],
        ],

        'error.reserved_namespace.unknown_directive_nested_must_fail' => [
            'title' => 'Reserved namespace guard: ["@foo" => "bar"] anywhere in a config map MUST fail (nested)',
            'inputs' => [
                'defaults' => [],
                'skeleton' => [],
                'module' => [
                    'http' => [
                        'security' => [
                            '@foo' => 'bar',
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_RESERVED_NAMESPACE_USED',
            ],
        ],

        'error.reserved_namespace.precedence_over_mixed_level' => [
            'title' => 'Error precedence: unknown @* key wins even if mixed-level is also violated',
            'inputs' => [
                'defaults' => [],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system' => [
                        '@foo' => 'bar',
                        'x' => 'y', // also mixed-level, but should NOT win
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_RESERVED_NAMESPACE_USED',
            ],
        ],

        // ---------------------------------------------------------------------
        // Exclusive-level rule failures (mixed-level / multi-directive)
        // ---------------------------------------------------------------------

        'error.mixed_level.directive_plus_normal_key_same_level' => [
            'title' => 'Exclusive-level rule: mixing directive key and normal key at same level MUST fail',
            'inputs' => [
                'defaults' => [],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system_post' => [
                        '@append' => ['Debugbar'],
                        'extra' => 'forbidden',
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL',
            ],
        ],

        'error.mixed_level.multiple_directives_same_level' => [
            'title' => 'Exclusive-level rule: multiple directives at the same level MUST fail',
            'inputs' => [
                'defaults' => [
                    'http.middleware.system_pre' => [
                        'CorrelationId',
                        'Maintenance',
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system_pre' => [
                        '@append' => ['Debugbar'],
                        '@remove' => ['Maintenance'],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL',
            ],
        ],

        // ---------------------------------------------------------------------
        // Typing rules failures (Phase A: directive value validation)
        // ---------------------------------------------------------------------

        'error.typing.append_requires_list_non_empty_map_is_forbidden' => [
            'title' => 'Typing rule: @append value MUST be a list (non-empty map MUST fail)',
            'inputs' => [
                'defaults' => [],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system_post' => [
                        '@append' => [
                            'k' => 'v', // map (array_is_list === false)
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH',
            ],
        ],

        'error.typing.prepend_requires_list_scalar_is_forbidden' => [
            'title' => 'Typing rule: @prepend value MUST be a list (scalar MUST fail)',
            'inputs' => [
                'defaults' => [],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system_post' => [
                        '@prepend' => 'ErrorHandling',
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH',
            ],
        ],

        'error.typing.merge_requires_map_list_is_forbidden' => [
            'title' => 'Typing rule: @merge value MUST be a map (list MUST fail)',
            'inputs' => [
                'defaults' => [
                    'http.features' => [
                        'request_id' => true,
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.features' => [
                        '@merge' => [
                            'a',
                            'b',
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH',
            ],
        ],

        // ---------------------------------------------------------------------
        // Merge-time type mismatch failures (Phase B: base kind mismatch)
        // ---------------------------------------------------------------------

        'error.merge_time.append_requires_base_list_base_is_map' => [
            'title' => 'Merge-time type mismatch: base is map but @append requires list',
            'inputs' => [
                'defaults' => [
                    'http.middleware.system' => [
                        'not' => 'a-list',
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.middleware.system' => [
                        '@append' => [
                            'Debugbar',
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH',
            ],
        ],

        'error.merge_time.merge_requires_base_map_base_is_list' => [
            'title' => 'Merge-time type mismatch: base is list but @merge requires map',
            'inputs' => [
                'defaults' => [
                    'http.features' => [
                        'a',
                        'b',
                    ],
                ],
                'skeleton' => [],
                'module' => [
                    'http.features' => [
                        '@merge' => [
                            'request_id' => true,
                        ],
                    ],
                ],
                'app' => [],
            ],
            'expect' => [
                'error_code' => 'CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH',
            ],
        ],

        // ---------------------------------------------------------------------
        // Middleware slot taxonomy presence checks (explicitly include all keys)
        // ---------------------------------------------------------------------

        'middleware.taxonomy.all_keys_present_under_app_via_plain_values' => [
            'title' => 'Middleware taxonomy: app provides all slot keys + auto (plain values, no directives)',
            'inputs' => [
                'defaults' => [],
                'skeleton' => [],
                'module' => [],
                'app' => [
                    'http.middleware.auto' => true,

                    'http.middleware.system_pre' => ['CorrelationId'],
                    'http.middleware.system' => [],
                    'http.middleware.system_post' => ['CacheHeaders'],

                    'http.middleware.app_pre' => ['Session'],
                    'http.middleware.app' => ['Router'],
                    'http.middleware.app_post' => [],

                    'http.middleware.route_pre' => [],
                    'http.middleware.route' => [],
                    'http.middleware.route_post' => [],
                ],
            ],
            'expect' => [
                'merged' => [
                    'http.middleware.app' => ['Router'],
                    'http.middleware.app_post' => [],
                    'http.middleware.app_pre' => ['Session'],
                    'http.middleware.auto' => true,
                    'http.middleware.route' => [],
                    'http.middleware.route_post' => [],
                    'http.middleware.route_pre' => [],
                    'http.middleware.system' => [],
                    'http.middleware.system_post' => ['CacheHeaders'],
                    'http.middleware.system_pre' => ['CorrelationId'],
                ],
            ],
        ],
    ],
];
