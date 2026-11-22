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
 * - redirect-decision observability is owned by HTTPS redirect middleware;
 * - maintenance-decision observability is owned by maintenance middleware;
 * - system-endpoint decision metrics are owned by the terminal FallbackRouter
 *   and remain independent of finalized request observability;
 * - maintenance configuration controls static middleware participation and
 *   the default Retry-After policy, not runtime maintenance activation;
 * - maintenance runtime state is application-root-local and independent of
 *   compiled configuration and generated artifacts;
 * - request-id preparation and identity-header finalization are governed by
 *   the HTTP-owned identity policy, independently of middleware configuration;
 * - security-header application is governed by an independent HTTP-owned
 *   policy within HttpResponseFinalizer, after identity-header finalization;
 * - security-header and HTTPS redirect enablement are independent;
 * - upstream request-id acceptance requires an explicitly trusted ingress;
 * - development diagnostics are disabled by default and governed by the
 *   HTTP-owned DebugGuard using authoritative Kernel runtime input;
 * - debug-decision observability is owned by DebugRouter and remains
 *   independent of system-endpoint and finalized request observability;
 * - no general application routing, transport, CORS, rate-limit, or upload
 *   configuration is declared;
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
     * HTTP-owned request identity policy.
     *
     * When enabled, request identity is selected before the PSR-15 pipeline
     * and applied to the completed response by HttpResponseFinalizer.
     * Request identity is independent of Kernel-owned correlation and
     * UnitOfWork identities.
     *
     * Upstream acceptance requires an explicitly trusted ingress boundary
     * that removes arbitrary client-supplied request-id headers or replaces
     * them with owner-approved opaque identifiers. Header presence and
     * identifier syntax do not establish upstream trust. Untrusted direct
     * client ingress must keep upstream acceptance disabled.
     *
     * Missing or invalid upstream identifiers are replaced with canonical
     * Foundation ULIDs. The maximum length applies to accepted upstream
     * identifier bytes and must be within 26..128 to permit generated ULIDs.
     * This policy does not activate middleware or introduce a reset lifecycle.
     */
    'request_id' => [
        'enabled' => false,
        'header_name' => 'X-Request-Id',
        'accept_from_upstream' => false,
        'max_length' => 128,
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
     * HTTP request-level, HTTPS redirect-decision, maintenance-decision,
     * terminal system-endpoint, and debug-decision metric recording.
     *
     * HttpKernel coordinates request-level metrics using the finalized
     * request outcome through the existing MeterPortInterface, outside
     * the PSR-15 middleware pipeline.
     * HTTPS redirect and maintenance middleware record their respective
     * completed decisions independently through the same metrics port.
     * FallbackRouter records completed system-endpoint dispatch decisions
     * independently of finalized request outcomes.
     * DebugRouter records authorized and blocked development diagnostics
     * decisions independently of system-endpoint decisions.
     *
     * This toggle governs all five metric boundaries.
     */
    'metrics' => [
        'enabled' => true,
    ],

    /*
     * HTTP request completion, HTTPS redirect-decision, maintenance,
     * and blocked debug-access logging.
     *
     * Request completion, redirect-decision, maintenance, and blocked
     * debug-access records are produced at independent HTTP-owned
     * observability boundaries.
     *
     * All use the existing PSR-3 logger and shared redaction boundary;
     * this does not select or require an optional Logging module.
     * This toggle governs all four logging boundaries, including safe
     * maintenance state failure diagnostics.
     */
    'access_log' => [
        'enabled' => true,
    ],

    /*
     * Application-root-local maintenance mode.
     *
     * The enabled toggle controls static middleware participation, not active
     * runtime maintenance state. Runtime activation is determined through
     * the existing maintenance state directory on each participating request.
     *
     * The fixed application-root-relative runtime state paths are:
     * - `var/maintenance/enabled`;
     * - `var/maintenance/message.json`;
     * - `var/maintenance/maintenance.lock`.
     *
     * Runtime state paths are not configurable. Runtime activation and
     * deactivation do not modify compiled configuration or generated artifacts.
     *
     * The default Retry-After value must be an integer in 1..86400 seconds.
     * A valid public maintenance message may supply an explicit override
     * within the same range.
     */
    'maintenance' => [
        'enabled' => true,
        'retry_after_default_seconds' => 60,
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
     * Development-only HTTP diagnostics endpoints.
     *
     * Disabled by default. Activation requires an authoritative `local`
     * or `dev` application environment and an explicitly selected canonical
     * Problem Details boundary.
     *
     * The IP allowlist contains exact IPv4 or IPv6 addresses, not networks.
     * An empty allowlist denies access.
     *
     * The debug token must be generated from 32 cryptographically secure
     * random bytes and represented as 64 lowercase hexadecimal characters.
     * Only its lowercase SHA-256 digest may be stored in `token_sha256`.
     * The raw token is supplied exclusively through the fixed X-Debug-Token
     * request header. An empty digest never authorizes access.
     *
     * Enabled paths select only the four canonical diagnostic operations.
     * Config diagnostics require an explicitly allowlisted logical key prefix.
     * An empty config allowlist denies config diagnostics.
     *
     * DebugGuard owns semantic validation, bounded allowlists, canonical IP
     * matching, token verification, and logical key-prefix authorization.
     * No raw configuration values or credentials may be disclosed.
     */
    'debug' => [
        'endpoints' => [
            'enabled' => false,
        ],
        'allowlist' => ['127.0.0.1', '::1'],
        'token_sha256' => '',
        'paths' => [
            'enabled' => ['headers', 'modules', 'config', 'cache'],
        ],
        'config' => [
            'allowlist' => [],
        ],
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
     * Canonical response security-header policy.
     *
     * When enabled, HttpResponseFinalizer applies configured non-empty
     * security headers after successful identity-header finalization.
     * Application is independent of middleware participation, request
     * identity enablement, and HTTPS redirect enablement.
     *
     * Configured non-empty values replace existing values for the same
     * headers case-insensitively. Empty optional values leave existing
     * application-provided headers unchanged.
     *
     * SecurityHeadersPolicy owns canonical directive validation and
     * bounded optional header-value semantics.
     */
    'security_headers' => [
        'enabled' => false,
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'deny',
        'referrer_policy' => 'no-referrer',
        'permissions_policy' => '',
        'csp' => '',
    ],

    /*
     * Optional HTTP-to-HTTPS redirect policy.
     *
     * Redirects apply only to eligible HTTP requests with explicitly
     * allowed methods. An empty method allowlist permits no redirects.
     * Effective scheme resolution uses canonical trusted-proxy metadata
     * when enabled and available, otherwise the request URI scheme.
     *
     * The target authority must be configured explicitly when enabled.
     * It accepts a canonical host with an optional explicit port, without
     * a scheme, userinfo, path, query, or fragment.
     *
     * The destination authority is never derived from the request URI,
     * Host header, forwarded host, ContextStore, or trusted-host allowlist.
     * Accepted encoded request paths and queries are preserved in Location.
     *
     * HTTPS redirect enablement is independent of security-header
     * enablement. Redirect-decision observability is governed by the
     * existing HTTP metrics and access-log toggles.
     */
    'https_redirect' => [
        'enabled' => false,
        'status' => 308,
        'allow_methods' => ['GET', 'HEAD'],
        'target_authority' => '',
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
