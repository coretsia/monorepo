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
 * Validation rules for the `errors` configuration subtree.
 *
 * These rules intentionally keep the Errors config strict:
 * - the file validates the `errors` root subtree;
 * - schemaVersion is fixed to `1`;
 * - unknown keys are rejected at every declared map level;
 * - reserved `@*` keys are rejected by the same strict-shape policy;
 * - `errors.reporting` is a required map containing only `enabled`,
 *   `min_severity`, `exclude_codes`, `sample_rate`, and `mapper_failures`;
 * - `errors.reporting.enabled` must be a boolean;
 * - `errors.reporting.min_severity` must be one of the canonical
 *   ErrorSeverity values: info, warning, error, or critical;
 * - `errors.reporting.exclude_codes` must be a list of non-empty strings
 *   without whitespace; an empty list is allowed;
 * - `errors.reporting.sample_rate` must be an integer in the inclusive
 *   range 0..10000, representing basis points;
 * - `errors.reporting.mapper_failures` is a required map containing
 *   only the boolean `enabled` key;
 * - all five reporting keys are required, including when reporting
 *   is disabled;
 * - numeric sampling bounds are enforced through the existing Kernel
 *   ConfigValidator `int`, `min`, and `max` rules;
 * - list items and allowed severity values are validated through the
 *   existing declarative ConfigValidator rules;
 * - reporter filtering applies only to normalized error reporting and
 *   does not modify mapper discovery, redaction, or mapping telemetry;
 * - mapper-failure diagnostics require global reporting to be enabled,
 *   but this runtime relationship does not require a custom validator;
 * - mapper registries, redaction disable switches, raw error-detail
 *   disclosure, environment-specific debug switches, and arbitrary
 *   sensitive-data masking policies are not declared configuration surfaces.
 *
 * The rules contain only declarative schema data. They MUST NOT define
 * executable validators, closures, objects, runtime service references,
 * or another configuration-validation pipeline.
 *
 * The defaults file must return the subtree only:
 *
 *     config/errors.php => ['reporting' => [...]]
 *
 * It must not return:
 *
 *     ['errors' => [...]]
 */
return [
    'schemaVersion' => 1,
    'configRoot' => 'errors',
    'additionalKeys' => false,
    'keys' => [
        'reporting' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'enabled' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'min_severity' => [
                    'required' => true,
                    'type' => 'string',
                    'allowedValues' => [
                        'info',
                        'warning',
                        'error',
                        'critical',
                    ],
                ],
                'exclude_codes' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
                'sample_rate' => [
                    'required' => true,
                    'type' => 'int',
                    'min' => 0,
                    'max' => 10000,
                ],
                'mapper_failures' => [
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
    ],
];
