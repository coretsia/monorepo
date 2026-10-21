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
 * Validation rules for the `cli` configuration subtree.
 *
 * These rules intentionally keep the CLI config strict:
 * - the file validates the `cli` root subtree;
 * - unknown keys are rejected at every declared map level except
 *   `cli.commands.overrides` and `cli.workflows.definitions`;
 * - `cli.commands` is a required map containing only `overrides`, not a
 *   command registry list;
 * - `cli.commands.overrides` is a required map with dynamic keys; ConfigValidator
 *   checks only its map shape, while CommandOverrides validates command names
 *   and the permitted summary, hidden, and group values;
 * - `cli.workflows` is a required map containing only `definitions`;
 * - `cli.workflows.definitions` is a required map with dynamic workflow-name
 *   keys; ConfigValidator checks only the map shape, while WorkflowSchema
 *   owns deep workflow definition validation;
 * - `cli.output` is a required map containing only `format_default`,
 *   `adaptive`, `color_default`, and `table`;
 * - `cli.output.format_default` must be adaptive, json, table, or plain;
 * - `cli.output.adaptive.interactive` and `non_interactive` must each be json,
 *   table, or plain;
 * - `cli.output.color_default` must be auto, always, or never;
 * - `cli.output.table.max_width` must be an integer in the range 40..240;
 * - legacy command registries, redaction toggles, runtime modes, UnitOfWork,
 *   observability settings, color palettes, and arbitrary ANSI values are not
 *   declared configuration surfaces.
 *
 * The defaults file must return the subtree only:
 *
 *     config/cli.php => ['commands' => [...], 'workflows' => [...], 'output' => [...]]
 *
 * It must not return:
 *
 *     ['cli' => [...]]
 */
return [
    'schemaVersion' => 1,
    'configRoot' => 'cli',
    'additionalKeys' => false,
    'keys' => [
        'commands' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'overrides' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => true,
                ],
            ],
        ],
        'workflows' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'definitions' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => true,
                ],
            ],
        ],
        'output' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'format_default' => [
                    'required' => true,
                    'type' => 'string',
                    'allowedValues' => [
                        'adaptive',
                        'json',
                        'table',
                        'plain',
                    ],
                ],
                'adaptive' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'interactive' => [
                            'required' => true,
                            'type' => 'string',
                            'allowedValues' => [
                                'json',
                                'table',
                                'plain',
                            ],
                        ],
                        'non_interactive' => [
                            'required' => true,
                            'type' => 'string',
                            'allowedValues' => [
                                'json',
                                'table',
                                'plain',
                            ],
                        ],
                    ],
                ],
                'color_default' => [
                    'required' => true,
                    'type' => 'string',
                    'allowedValues' => [
                        'auto',
                        'always',
                        'never',
                    ],
                ],
                'table' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'max_width' => [
                            'required' => true,
                            'type' => 'int',
                            'min' => 40,
                            'max' => 240,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
