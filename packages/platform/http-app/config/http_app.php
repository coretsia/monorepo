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
 * HttpApp runtime defaults.
 *
 * This file returns the `http_app` configuration subtree only.
 * It MUST NOT wrap values in a repeated root key such as:
 *
 *     return ['http_app' => [...]];
 *
 * Runtime code reads these values from the merged global configuration under
 * `http_app.*`.
 *
 * Baseline invariants:
 * - the `http_app` root is owned by `platform/http-app`;
 * - module participation is determined by the authoritative ModulePlan;
 * - action handlers and route-specific middleware require explicit service admission;
 * - handler registrations and admission lists are configuration values;
 * - route-specific middleware order is owned by route match metadata;
 * - semantic admission is provider-owned and uses the effective configuration;
 * - request and route parameter injection are independently configurable;
 * - concrete service argument injection is opt-in through an explicit allowlist;
 * - JSON and HTML response mapping are independently configurable;
 * - tracing, metrics, and logging are independently configurable;
 * - observability flags MUST NOT affect action execution or error propagation;
 * - defaults contain no environment reads, service objects, or runtime wiring.
 */
return [
    /*
     * Explicit action handler registrations.
     *
     * Each list entry is a closed map containing exactly `action_id` and
     * `service_id`, both non-empty strings. Logical action ids are unique
     * and are values, not structural configuration keys. Service ids refer
     * to explicit canonical container registrations. No implicit class-name
     * or service-id fallback is supported.
     */
    'handlers' => [],

    /*
     * Route-specific middleware admission list.
     *
     * Contains unique non-empty canonical service-id strings for explicitly
     * registered middleware services. The list grants admission only; the
     * execution order is owned by RouteMatch::metadata()['http_app_middleware'].
     * Canonical routing, dispatch, and outer error-boundary roles are not
     * eligible for route-specific execution.
     */
    'route_middleware' => [],

    /*
     * Action argument resolution policy.
     *
     * Only explicitly admitted argument sources and declared PHP defaults
     * participate in resolution.
     */
    'arguments' => [
        /*
         * Enable explicit HTTP request injection for the exact
         * ServerRequestInterface named parameter type.
         */
        'allow_request' => true,

        /*
         * Enable matched route parameter injection by exact parameter name.
         * Values remain encoded strings; no decoding or coercion is applied.
         */
        'allow_route_params' => true,

        /*
         * Explicit allowlist of unique concrete class names for container
         * argument injection. Each type requires an explicit service binding.
         */
        'allow_container_types' => [],
    ],

    /*
     * Action response mapping policy.
     *
     * Existing ResponseInterface values pass through unchanged.
     * Disabled representation mapping fails closed.
     */
    'response' => [
        'json' => [
            /*
             * Enable canonical JSON mapping of null, bool, int, and array
             * action return values through StableJsonEncoder.
             */
            'enabled' => true,
        ],
        'html' => [
            /*
             * Enable exact application-provided HTML string responses.
             * Escaping untrusted application data remains action-owned.
             */
            'enabled' => true,
        ],
    ],

    /*
     * Action execution tracing.
     *
     * Disabling tracing MUST NOT affect action execution or other signals.
     */
    'tracing' => [
        /* Enable HttpApp-owned action tracing. */
        'enabled' => true,
    ],

    /*
     * Action execution metrics.
     *
     * Disabling metrics MUST NOT affect action execution or other signals.
     */
    'metrics' => [
        /* Enable HttpApp-owned action metrics. */
        'enabled' => true,
    ],

    /*
     * Action completion logging.
     *
     * Logs MUST follow the canonical sensitive-data redaction policy.
     * Disabling logging MUST NOT affect action execution or other signals.
     */
    'logging' => [
        /* Enable HttpApp-owned action completion logs. */
        'enabled' => true,
    ],
];
