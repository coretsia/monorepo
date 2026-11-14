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
 * Platform HTTP runtime defaults.
 *
 * This file returns the `http` configuration subtree only.
 * It MUST NOT wrap values in a repeated root key such as:
 *
 *     return ['http' => [...]];
 *
 * Runtime code reads these values from the merged global configuration under
 * `http.*`. The effective target-local configuration is resolved by the
 * existing ConfigKernel Phase B precedence and compiled into `config@1`.
 *
 * Baseline invariants:
 * - the `http` root is owned by `platform/http`;
 * - module participation belongs to Kernel ModulePlan, not these defaults;
 * - `http.factory` explicitly selects PSR-17 bindings, not installed packages;
 * - the consuming application or distribution installs compatible concrete
 *   PSR-7/17 implementations;
 * - `external` preserves application/distribution-owned PSR-17 bindings;
 * - built-in factory selections use fixed HTTP-owned declarative DI mappings;
 * - no Composer discovery, automatic factory fallback, or concrete factory
 *   class names are configured here;
 * - the nine middleware lists contain only manually appended DI service ids;
 * - tagged middleware is enumerated through the canonical Foundation
 *   TagRegistry and ordered by the existing priority/service-id policy;
 * - optional middleware is governed by feature-specific validated toggles,
 *   not `http.middleware.auto.*` configuration;
 * - middleware execution order, PSR-15 response decoration, and the reserved
 *   outermost error boundary belong to the HTTP middleware stack builder;
 * - Kernel retains UnitOfWork and reset lifecycle ownership;
 * - request-level final-response observability is not middleware configuration;
 * - no routing, system-endpoint, transport, security-header, CORS, rate-limit,
 *   upload, maintenance, request-id, or debugging configuration is declared;
 * - keys beginning with `@` are reserved and rejected by config rules.
 */
return [
    /*
     * Explicit PSR-17 factory selection.
     *
     * Allowed values:
     *
     * - `external`: use application/distribution-owned PSR-17 DI bindings.
     * - `nyholm`: use the fixed Nyholm PSR-17 factory mapping.
     * - `guzzlehttp`: use the fixed Guzzle PSR-17 factory mapping.
     * - `laminas`: use the fixed Laminas PSR-17 factory mapping.
     * - `slim`: use the fixed Slim PSR-17 factory mapping.
     *
     * `external` contributes no concrete factory definitions or aliases.
     * Built-in selections contribute only their selected declarative bindings.
     * Installed packages never change this selection automatically. Missing or
     * incompatible selected implementations fail HTTP readiness without fallback.
     *
     * Changing this target-local value participates in normal Phase B config,
     * compiled DI definitions, and artifact fingerprint/invalidation behavior.
     */
    'factory' => 'external',

    /*
     * Manual PSR-15 middleware append lists.
     *
     * Every entry is a middleware class-string used as an exact DI service id.
     * Tagged middleware comes first in canonical TagRegistry order; these
     * configured ids are appended after the tagged portion of their slot.
     * The canonical container resolves either an explicit service definition
     * or Foundation-permitted concrete-class autowiring. HTTP introduces no
     * separate reflection, service registration, or fallback resolution layer.
     *
     * Request-phase slots run in this logical order:
     *
     * - `system_pre`, `system`, `app_pre`, `app`, `route_pre`, `route`.
     *
     * The terminal handler follows the request phase. Response decorators
     * unwind in `route_post`, `app_post`, `system_post` order. The HTTP stack
     * builder wraps the request phase with post decorators so returned early
     * short-circuit responses pass through applicable post slots. An explicitly
     * selected priority-1000 error boundary remains outermost; exceptions
     * converted there are outside the ordinary post-decorator unwind.
     */
    'middleware' => [
        /* System request middleware before the main system slot. */
        'system_pre' => [],

        /* Main system request middleware. */
        'system' => [],

        /* Outermost response decorators; unwind after app and route decorators. */
        'system_post' => [],

        /* Application request middleware before the main application slot. */
        'app_pre' => [],

        /* Main application request middleware. */
        'app' => [],

        /* Application response decorators; unwind after route decorators. */
        'app_post' => [],

        /* Route-phase request middleware before the main route slot. */
        'route_pre' => [],

        /* Main route-phase request middleware before the terminal handler. */
        'route' => [],

        /* Innermost response decorators; first to unwind on returned responses. */
        'route_post' => [],
    ],

    /*
     * Canonical correlation response header policy.
     *
     * The correlation id is Kernel-owned. HTTP response finalization may add
     * its existing value to the completed response using `header_name`.
     * The header name is subject to HTTP-owned bounded token validation.
     * Disabling this policy does not disable Kernel correlation generation.
     */
    'correlation' => [
        'enabled' => true,
        'header_name' => 'X-Correlation-Id',
    ],

    /*
     * Inbound W3C trace context and request-span participation.
     *
     * TraceContextMiddleware uses the existing propagation port when enabled;
     * the HTTP request span uses the existing tracing port. No tracing provider,
     * parent-context registry, exporter, or reset lifecycle is selected here.
     */
    'tracing' => [
        'enabled' => true,
    ],

    /*
     * HTTP request-level metric recording.
     *
     * Metrics consume the finalized request outcome through the existing
     * MeterPortInterface, outside the PSR-15 middleware pipeline.
     */
    'metrics' => [
        'enabled' => true,
    ],

    /*
     * HTTP request completion access logging.
     *
     * Records use the existing PSR-3 logger and shared redaction boundary;
     * this does not select or require an optional Logging module.
     */
    'access_log' => [
        'enabled' => true,
    ],

    /*
     * Trusted proxy interpretation.
     *
     * Forwarded metadata is accepted only when enabled and the immediate peer
     * matches an explicitly configured canonical IP/CIDR entry. The trusted
     * proxy policy owns IP/CIDR grammar, bounded list semantics, and forwarded
     * header interpretation; untrusted headers are never authoritative.
     */
    'proxy' => [
        'enabled' => false,
        'trusted_proxies' => [],
    ],

    /*
     * Request-safe ContextStore enrichment.
     *
     * Enrichment uses validated request/proxy metadata and existing ContextKeys.
     * It must not overwrite Kernel-owned correlation or UnitOfWork identifiers.
     */
    'context' => [
        'enrich' => [
            'enabled' => true,
        ],
    ],

    /*
     * Trusted request-host allowlist.
     *
     * When enabled, the HTTP-owned TrustedHostPolicy validates the effective
     * host against these canonical, bounded host entries. An empty allowlist
     * does not implicitly trust arbitrary hosts when checking is enabled.
     */
    'hosts' => [
        'enabled' => false,
        'allowed' => [],
    ],

    /*
     * Inbound request body safety and JSON parsing.
     *
     * `max_body_bytes` is a positive, bounded byte budget enforced by the
     * HTTP body-size middleware before parsing. The middleware verifies actual
     * request-stream bytes and preserves accepted downstream body contents.
     *
     * JSON parsing is limited to eligible JSON content types, uses PHP's
     * json_decode() with the configured positive bounded `max_depth`, and
     * publishes accepted object/array roots through PSR-7 parsed-body data.
     * The raw body is not copied into shared context or diagnostics.
     */
    'request' => [
        'max_body_bytes' => 1048576,
        'json' => [
            'enabled' => true,
            'max_depth' => 32,
        ],
    ],
];
