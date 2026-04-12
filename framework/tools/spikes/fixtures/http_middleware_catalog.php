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
 * Notes (normative for this fixture):
 *  - Slot keys MUST match the Phase 0 spikes slot taxonomy:
 *    http.middleware.{system|app|route}_{pre|post} plus {system|app|route}.
 *  - Each slot list is stored in canonical order (priority DESC as authored here).
 *  - This is documentation/fixture data only: middleware classes are stored as FQCN strings.
 */

return [
    'schema_version' => 1,

    'slots' => [
        // Slot: http.middleware.system_pre
        'http.middleware.system_pre' => [
            [
                'priority' => 950,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\CorrelationIdMiddleware::class,
                'activation' => 'auto',
                'toggle' => null,
                'notes' => 'auto',
            ],
            [
                'priority' => 940,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\RequestIdMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.request_id.enabled',
                'notes' => 'auto if http.request_id.enabled',
            ],
            [
                'priority' => 930,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\TraceContextMiddleware::class,
                'activation' => 'auto',
                'toggle' => null,
                'notes' => 'auto',
            ],
            [
                'priority' => 920,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\HttpMetricsMiddleware::class,
                'activation' => 'auto',
                'toggle' => null,
                'notes' => 'auto',
            ],
            [
                'priority' => 910,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\AccessLogMiddleware::class,
                'activation' => 'auto',
                'toggle' => null,
                'notes' => 'auto',
            ],
            [
                'priority' => 900,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Maintenance\MaintenanceMiddleware::class,
                'activation' => 'auto_if_enabled',
                'toggle' => null,
                'notes' => 'auto if enabled',
            ],
            [
                'priority' => 880,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\TrustedProxyMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.proxy.enabled',
                'notes' => 'auto if http.proxy.enabled',
            ],
            [
                'priority' => 870,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\RequestContextMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.context.enrich.enabled',
                'notes' => 'auto if http.context.enrich.enabled',
            ],
            [
                'priority' => 860,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\TrustedHostMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.hosts.enabled',
                'notes' => 'auto if http.hosts.enabled',
            ],
            [
                'priority' => 850,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\HttpsRedirectMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.https_redirect.enabled',
                'notes' => 'auto if http.https_redirect.enabled',
            ],
            [
                'priority' => 840,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\CorsMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.cors.enabled',
                'notes' => 'auto if http.cors.enabled',
            ],
            [
                'priority' => 830,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\RequestBodySizeLimitMiddleware::class,
                'activation' => 'auto',
                'toggle' => null,
                'notes' => 'auto',
            ],
            [
                'priority' => 820,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\MethodOverrideMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.method_override.enabled',
                'notes' => 'auto if http.method_override.enabled',
            ],
            [
                'priority' => 810,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\ContentNegotiationMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.negotiation.enabled',
                'notes' => 'auto if http.negotiation.enabled',
            ],
            [
                'priority' => 800,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\JsonBodyParserMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.request.json.enabled',
                'notes' => 'auto if http.request.json.enabled',
            ],
            [
                'priority' => 790,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\EarlyRateLimitMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.rate_limit.early.enabled',
                'notes' => 'auto if http.rate_limit.early.enabled; identity-aware placement',
            ],
            [
                'priority' => 580,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Debug\DebugEndpointsMiddleware::class,
                'activation' => 'opt_in',
                'toggle' => null,
                'notes' => 'OPTIONAL SHAPE; dev-only; must be opt-in',
            ],

            // Optional packages (not referenced by platform/http defaults; self-register if installed).
            [
                'priority' => 1000,
                'owner' => 'platform/problem-details',
                'class' => \Coretsia\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware::class,
                'activation' => 'self_register_if_installed',
                'toggle' => null,
                'notes' => 'optional package; SHOULD self-register if installed',
            ],
            [
                'priority' => 600,
                'owner' => 'platform/health',
                'class' => \Coretsia\Health\Http\Middleware\HealthEndpointsMiddleware::class,
                'activation' => 'self_register_if_installed',
                'toggle' => null,
                'notes' => 'optional package; SHOULD self-register if installed',
            ],
            [
                'priority' => 590,
                'owner' => 'platform/metrics',
                'class' => \Coretsia\Metrics\Http\Middleware\MetricsEndpointMiddleware::class,
                'activation' => 'self_register_if_installed',
                'toggle' => null,
                'notes' => 'optional package; SHOULD self-register if installed',
            ],
        ],

        // Slot: http.middleware.system (reserved; empty by default)
        'http.middleware.system' => [],

        // Slot: http.middleware.system_post
        'http.middleware.system_post' => [
            [
                'priority' => 900,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\CacheHeadersMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.cache_headers.enabled',
                'notes' => 'auto if http.cache_headers.enabled',
            ],
            [
                'priority' => 800,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\EtagMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.etag.enabled',
                'notes' => 'auto if http.etag.enabled',
            ],
            [
                'priority' => 700,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\CompressionMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.compression.enabled',
                'notes' => 'auto if http.compression.enabled',
            ],
            [
                'priority' => 650,
                'owner' => 'platform/streaming',
                'class' => \Coretsia\Streaming\Http\Middleware\DisableBufferingMiddleware::class,
                'activation' => 'auto_if_enabled',
                'toggle' => null,
                'notes' => 'auto if enabled',
            ],
            [
                'priority' => 600,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\SecurityHeadersMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.security_headers.enabled',
                'notes' => 'auto if http.security_headers.enabled',
            ],
            [
                'priority' => 500,
                'owner' => 'devtools/dev-tools',
                'class' => \Coretsia\DevTools\Http\Middleware\DebugbarMiddleware::class,
                'activation' => 'opt_in',
                'toggle' => null,
                'notes' => 'SHAPE; dev-only',
            ],
        ],

        // Slot: http.middleware.app_pre
        'http.middleware.app_pre' => [
            [
                'priority' => 500,
                'owner' => 'enterprise/tenancy',
                'class' => \Coretsia\Tenancy\Http\Middleware\TenantContextMiddleware::class,
                'activation' => 'auto_if_enabled',
                'toggle' => null,
                'notes' => 'auto if enabled',
            ],
            [
                'priority' => 350,
                'owner' => 'platform/async',
                'class' => \Coretsia\Async\Http\Middleware\RequestTimeoutMiddleware::class,
                'activation' => 'auto_if_enabled',
                'toggle' => null,
                'notes' => 'auto if enabled',
            ],
            [
                'priority' => 300,
                'owner' => 'platform/session',
                'class' => \Coretsia\Session\Http\Middleware\SessionMiddleware::class,
                'activation' => 'auto',
                'toggle' => null,
                'notes' => 'auto',
            ],
            [
                'priority' => 200,
                'owner' => 'platform/auth',
                'class' => \Coretsia\Auth\Http\Middleware\AuthMiddleware::class,
                'activation' => 'auto',
                'toggle' => null,
                'notes' => 'auto',
            ],
            [
                'priority' => 150,
                'owner' => 'platform/http',
                'class' => \Coretsia\Http\Middleware\RateLimitMiddleware::class,
                'activation' => 'auto',
                'toggle' => 'http.rate_limit.identity.enabled',
                'notes' => 'auto if http.rate_limit.identity.enabled; identity-aware placement',
            ],
            [
                'priority' => 100,
                'owner' => 'platform/security',
                'class' => \Coretsia\Security\Http\Middleware\CsrfMiddleware::class,
                'activation' => 'auto',
                'toggle' => null,
                'notes' => 'auto',
            ],
            [
                'priority' => 80,
                'owner' => 'platform/uploads',
                'class' => \Coretsia\Uploads\Http\Middleware\MultipartFormDataMiddleware::class,
                'activation' => 'auto_if_enabled',
                'toggle' => null,
                'notes' => 'auto if enabled',
            ],
            [
                'priority' => 50,
                'owner' => 'platform/inbox',
                'class' => \Coretsia\Inbox\Http\Middleware\IdempotencyKeyMiddleware::class,
                'activation' => 'auto_if_enabled',
                'toggle' => null,
                'notes' => 'auto if enabled',
            ],
        ],

        // Slot: http.middleware.app
        'http.middleware.app' => [
            [
                'priority' => 100,
                'owner' => 'platform/http-app',
                'class' => \Coretsia\HttpApp\Middleware\RouterMiddleware::class,
                'activation' => 'auto',
                'toggle' => null,
                'notes' => 'auto',
            ],
        ],

        // Slot: http.middleware.app_post (reserved; empty by default)
        'http.middleware.app_post' => [],

        // Slot: http.middleware.route_pre (reserved; empty by default)
        'http.middleware.route_pre' => [],

        // Slot: http.middleware.route (reserved; empty by default)
        'http.middleware.route' => [],

        // Slot: http.middleware.route_post (reserved; empty by default)
        'http.middleware.route_post' => [],
    ],

    // Opt-in middlewares (MUST NOT be auto-wired)
    'opt_in' => [
        'platform/auth' => [
            \Coretsia\Auth\Http\Middleware\RequireAuthMiddleware::class,
            \Coretsia\Auth\Http\Middleware\RequireAbilityMiddleware::class,
        ],
    ],
];
