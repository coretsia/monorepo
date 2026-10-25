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
 * Errors runtime defaults.
 *
 * This file returns the `errors` configuration subtree only.
 * It MUST NOT wrap values in a repeated root key such as:
 *
 *     return ['errors' => [...]];
 *
 * Runtime code reads these values from the merged global configuration under
 * `errors.*`.
 *
 * Baseline invariants:
 * - the `errors` root is owned by `platform/errors`;
 * - exception mapper discovery uses the reserved `error.mapper` DI tag, not
 *   a configuration-based mapper registry;
 * - error normalization, deterministic mapper dispatch, safe fallback, and
 *   sensitive-data redaction are mandatory runtime behavior;
 * - reporting is optional and disabled by default;
 * - reporter severity, error-code exclusion, and sampling filters apply only
 *   after final safe ErrorDescriptor selection;
 * - reporter filters MUST NOT alter mapper ordering, descriptor values,
 *   fallback behavior, redaction, or mapping telemetry;
 * - mapper-failure diagnostics are independently configurable but require
 *   global reporting to be enabled;
 * - reporter, logger, tracer, and meter integrations use existing injected
 *   observability ports rather than package-local implementations;
 * - configuration is validated by the canonical Kernel ConfigValidator and
 *   consumed through ConfigRepositoryInterface at service construction;
 * - no configuration key may disable redaction, expose raw Throwable details,
 *   or introduce a second normalization or reporting pipeline;
 * - defaults contain no environment reads, runtime service references,
 *   dynamically discovered mapper classes, or invocation-local state;
 * - keys beginning with `@` are reserved and rejected by config rules.
 */
return [
    /*
     * Optional error reporting and mapping observability policy.
     *
     * These settings control optional telemetry, normalized error reporting,
     * and safe mapper-failure diagnostics. They do not disable the canonical
     * Throwable-to-ErrorDescriptor normalization pipeline.
     *
     * All values are validated by Kernel ConfigValidator and consumed by
     * ErrorsServiceFactory during runtime service construction.
     */
    'reporting' => [
        /*
         * Enable optional error reporting and mapping observability.
         *
         * When false, reporter, logger, tracer, and meter emissions owned by
         * this package are disabled.
         *
         * When true, the existing injected observability ports are used.
         * Noop implementations remain valid baseline bindings.
         *
         * Mapping, mandatory redaction, and fallback selection remain active
         * regardless of this setting.
         */
        'enabled' => false,

        /*
         * Minimum severity eligible for normalized error reporting.
         *
         * Allowed: info, warning, error, critical.
         * Canonical order: info < warning < error < critical.
         *
         * Only final descriptors with severity at or above this threshold
         * may be passed to ErrorReporterPortInterface.
         *
         * The default `info` preserves all canonical severity levels.
         * This filter does not suppress mapping telemetry or diagnostics.
         */
        'min_severity' => 'info',

        /*
         * Error codes excluded from normalized error reporting.
         *
         * Values must be non-empty strings without whitespace.
         *
         * Codes are matched exactly and case-sensitively against the final
         * redacted and validated ErrorDescriptor code.
         *
         * An empty list excludes no codes.
         *
         * This list does not control mapper discovery, exception mapping,
         * redaction, fallback selection, or mapping telemetry.
         */
        'exclude_codes' => [],

        /*
         * Probabilistic sampling rate for normalized error reporting.
         *
         * Integer basis points in the inclusive range 0..10000:
         *
         * - 0: suppress every reporter invocation;
         * - 5000: approximately 50% of eligible reporter invocations;
         * - 10000: report every otherwise eligible descriptor.
         *
         * Sampling occurs only after minimum-severity and excluded-code
         * filtering, using the final safe descriptor.
         *
         * Sampling affects only ErrorReporterPortInterface invocation.
         * It does not affect normalization, redaction, mapper diagnostics,
         * mapping spans, metrics, or the returned descriptor.
         */
        'sample_rate' => 10000,

        /*
         * Internal exception mapper failure diagnostics.
         *
         * Mapper candidate failures remain isolated regardless of whether
         * diagnostic logging is enabled.
         *
         * These diagnostics are independent of reporter severity, excluded
         * codes, and sampling filters.
         */
        'mapper_failures' => [
            /*
             * Enable safe mapper-failure logging.
             *
             * Effective only when reporting.enabled is also true.
             *
             * Logged diagnostics use fixed safe event names and reason
             * tokens. Raw Throwable messages, traces, and sensitive
             * exception data must never be logged.
             *
             * Disabling diagnostics must not affect mapper dispatch,
             * discovery continuation, or fallback behavior.
             */
            'enabled' => false,
        ],
    ],
];
