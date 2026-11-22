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
 * - correlation, request-id, upstream request-id acceptance, tracing, metrics,
 *   access logging, maintenance, proxy, debug endpoints, context enrichment,
 *   host validation, security headers, HTTPS redirect, and JSON parsing
 *   toggles must be actual booleans;
 * - `http.maintenance.retry_after_default_seconds` must be an integer
 *   in 1..86400;
 * - `http.correlation.header_name` and `http.request_id.header_name` must be
 *   non-empty and whitespace-free;
 * - `http.request_id.max_length` must be an integer in 26..128;
 * - `http.proxy.trusted_proxies` and `http.hosts.allowed` must be lists of
 *   non-empty, whitespace-free strings;
 * - security-header directives must match their canonical allowed values;
 * - optional security-header values and HTTPS target authority must be strings;
 * - `http.https_redirect.status` must be exactly integer `307` or `308`;
 * - `http.https_redirect.allow_methods` must be a list of supported canonical
 *   uppercase HTTP method strings;
 * - `http.debug.allowlist` must be a list of non-empty, whitespace-free strings;
 * - `http.debug.token_sha256` must be a string;
 * - `http.debug.paths.enabled` must be a list restricted to the four canonical
 *   operations: headers, modules, config, and cache;
 * - `http.debug.config.allowlist` must be a list of non-empty, whitespace-free
 *   logical key-prefix strings;
 * - DebugGuard owns semantic IP, token digest, logical key grammar, uniqueness,
 *   and bounded-list validation independently of feature activation;
 * - HTTP-owned policies validate canonical header tokens, request identity
 *   semantics, IP/CIDR addresses, host syntax, entry lengths, bounded list
 *   limits, redirect method uniqueness, target authority semantics, and
 *   bounded optional security-header values;
 * - ConfigValidator supplies only existing declarative structural, type,
 *   allowed-value, and integer range rules;
 * - structural validation applies independently of feature enablement;
 * - semantic policy validation executes during policy construction;
 * - SecurityHeadersPolicy is validated independently of enablement;
 * - an enabled HttpsRedirectPolicy is validated during canonical middleware
 *   resolution before request execution; disabled redirect configuration
 *   does not require eager policy resolution solely for semantic validation;
 * - `http.request.max_body_bytes` must be an integer in 1..16777216;
 * - `http.request.json.max_depth` must be an integer in 1..512;
 * - no `http.middleware.auto.*`, implicit factory selection, implementation
 *   class-name, Composer installation, optional module activation, general
 *   application routing, or environment-specific disclosure override keys
 *   are declared.
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
        'request_id' => [
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
                'accept_from_upstream' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'max_length' => [
                    'required' => true,
                    'type' => 'int',
                    'min' => 26,
                    'max' => 128,
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
        'maintenance' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'retry_after_default_seconds' => [
                    'required' => true,
                    'type' => 'int',
                    'min' => 1,
                    'max' => 86400,
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
        'debug' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'endpoints' => [
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
                'allowlist' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'token_sha256' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'paths' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'enabled' => [
                            'required' => true,
                            'type' => 'list',
                            'items' => [
                                'type' => 'string',
                                'allowedValues' => [
                                    'headers',
                                    'modules',
                                    'config',
                                    'cache',
                                ],
                            ],
                        ],
                    ],
                ],
                'config' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'allowlist' => [
                            'required' => true,
                            'type' => 'list',
                            'items' => [
                                'type' => 'non-empty-string-no-ws',
                            ],
                        ],
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
        'security_headers' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'x_content_type_options' => [
                    'required' => true,
                    'type' => 'string',
                    'allowedValues' => [
                        'nosniff',
                    ],
                ],
                'x_frame_options' => [
                    'required' => true,
                    'type' => 'string',
                    'allowedValues' => [
                        'deny',
                        'sameorigin',
                    ],
                ],
                'referrer_policy' => [
                    'required' => true,
                    'type' => 'string',
                    'allowedValues' => [
                        'no-referrer',
                        'no-referrer-when-downgrade',
                        'origin',
                        'origin-when-cross-origin',
                        'same-origin',
                        'strict-origin',
                        'strict-origin-when-cross-origin',
                        'unsafe-url',
                    ],
                ],
                'permissions_policy' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'csp' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ],
        'https_redirect' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'status' => [
                    'required' => true,
                    'type' => 'int',
                    'allowedValues' => [
                        307,
                        308,
                    ],
                ],
                'allow_methods' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'string',
                        'allowedValues' => [
                            'GET',
                            'HEAD',
                            'POST',
                            'PUT',
                            'PATCH',
                            'DELETE',
                            'OPTIONS',
                        ],
                    ],
                ],
                'target_authority' => [
                    'required' => true,
                    'type' => 'string',
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
