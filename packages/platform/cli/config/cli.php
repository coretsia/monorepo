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
 * CLI runtime defaults.
 *
 * This file returns the `cli` configuration subtree only.
 * It MUST NOT wrap values in a repeated root key such as:
 *
 *     return ['cli' => [...]];
 *
 * Runtime code reads these values from the merged global configuration under
 * `cli.*`.
 *
 * Baseline invariants:
 * - the `cli` root is owned by `platform/cli`;
 * - command discovery uses the `cli.command` DI tag, not a config registry;
 * - command overrides affect only catalog presentation and are validated by
 *   CommandOverrides after structural config validation;
 * - output formatting and color policy are resolved per invocation;
 * - redaction is mandatory and cannot be disabled through CLI configuration;
 * - module selection, UnitOfWork, and observability remain Kernel-owned;
 * - defaults contain no environment reads, terminal detection, or runtime values.
 */
return [
    /*
     * Command catalog presentation overrides.
     *
     * Commands are discovered through the `cli.command` DI tag, not this map.
     * An empty overrides map leaves command descriptors unchanged.
     */
    'commands' => [
        /*
         * Per-command catalog-view overrides.
         *
         * Dynamic command-name keys and their values are validated by
         * CommandOverrides; structural config rules validate only the map.
         * Only summary, hidden, and group may be overridden.
         */
        'overrides' => [],
    ],
    /*
     * Default CLI output presentation policy.
     *
     * These values do not select the runtime module, probe the terminal, or
     * control sensitive-data redaction.
     */
    'output' => [
        /*
         * Default output format.
         *
         * Allowed: adaptive, json, table, plain.
         * `adaptive` resolves from terminal capabilities later.
         */
        'format_default' => 'adaptive',

        /*
         * Adaptive format choices.
         *
         * The terminal capabilities are evaluated per invocation; this file
         * contains only deterministic fallback format tokens.
         */
        'adaptive' => [
            /*
             * Format for interactive stdout.
             *
             * Allowed: json, table, plain.
             */
            'interactive' => 'table',

            /*
             * Format for non-interactive stdout.
             *
             * Allowed: json, table, plain.
             */
            'non_interactive' => 'plain',
        ],

        /*
         * Default ANSI color policy.
         *
         * Allowed: auto, always, never. Effective color is resolved from
         * invocation-local terminal capabilities and the selected format.
         */
        'color_default' => 'auto',

        /*
         * Deterministic table-rendering limits.
         */
        'table' => [
            /*
             * Maximum table width in display columns.
             *
             * Config rules require an integer in the range 40..240.
             * This limit does not depend on the detected terminal width.
             */
            'max_width' => 120,
        ],
    ],
];
