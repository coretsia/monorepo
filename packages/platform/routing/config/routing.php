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
 * Routing runtime defaults.
 *
 * This file returns the `routing` configuration subtree only.
 * It MUST NOT wrap values in a repeated root key such as:
 *
 *     return ['routing' => [...]];
 *
 * Runtime code reads these values from the merged global configuration under
 * `routing.*`.
 *
 * Baseline invariants:
 * - the `routing` root is owned by `platform/routing`;
 * - tracing, metrics, and logging are independently configurable;
 * - all observability signals are enabled by default;
 * - observability flags MUST NOT affect route matching or artifact validation;
 * - Routing participation is determined by the authoritative ModulePlan;
 * - route compilation is an explicitly invoked operation;
 * - runtime matching consumes only the validated compiled routes artifact;
 * - Routing artifact readiness cannot be disabled through configuration;
 * - reserved-route protection cannot be disabled through configuration;
 * - artifact paths are derived from the authoritative RuntimePathContext;
 * - defaults contain no environment reads, filesystem discovery, or runtime values.
 */
return [
    /*
     * Routing match tracing.
     *
     * Controls tracing signals emitted during runtime route matching.
     *
     * Disabling tracing MUST NOT affect route matching, route compilation,
     * artifact validation, or Routing artifact readiness.
     */
    'tracing' => [
        /*
         * Enable Routing tracing.
         *
         * Tracing is enabled by default. When disabled, Routing does not
         * emit its own tracing signals through TracerPortInterface.
         */
        'enabled' => true,
    ],

    /*
     * Routing match metrics.
     *
     * Controls metrics emitted during runtime route matching.
     *
     * Disabling metrics MUST NOT affect matching results, route ordering,
     * artifact validation, or other observability signals.
     */
    'metrics' => [
        /*
         * Enable Routing metrics.
         *
         * Metrics are enabled by default. When disabled, Routing does not
         * emit its own metrics through MeterPortInterface.
         */
        'enabled' => true,
    ],

    /*
     * Routing diagnostic logging.
     *
     * Controls diagnostic log records produced by Routing.
     *
     * Logging MUST follow the canonical sensitive-data redaction policy.
     * Disabling logging MUST NOT affect route matching, artifact validation,
     * or the operation of tracing and metrics.
     */
    'logging' => [
        /*
         * Enable Routing diagnostic logging.
         *
         * Logging is enabled by default. When disabled, Routing does not
         * emit its own diagnostic records through LoggerInterface.
         */
        'enabled' => true,
    ],
];
