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
 * Validation rules for the `kernel` configuration subtree.
 *
 * These rules intentionally keep the initial Kernel config strict:
 * - the file validates the `kernel` root subtree;
 * - unknown keys are rejected at every declared map level;
 * - reserved `@*` keys are rejected by the same strict-shape policy;
 * - `kernel.boot.default_env` must be a non-empty string;
 * - `kernel.boot.default_preset` must be a non-empty string;
 * - `kernel.boot.default_debug` must be a bool;
 * - `kernel.env.source_policy.default_local` must be `strict_dotenv`;
 * - `kernel.env.source_policy.default_production` must be `allow_system`;
 * - `kernel.env.dotenv.files` must be a list of non-empty strings;
 * - `kernel.uow.attributes.max_depth` must be an integer greater than zero;
 * - `kernel.uow.attributes.max_keys` must be an integer greater than zero;
 * - this epic introduces no runtime feature flags, reset tag constants, HTTP
 *   adapter config, CLI adapter config, artifact config, or outcome mapping
 *   config.
 *
 * The defaults file must return the subtree only:
 *
 *     config/kernel.php => ['boot' => [...], 'env' => [...], 'uow' => [...]]
 *
 * It must not return:
 *
 *     ['kernel' => [...]]
 */
return [
    'schemaVersion' => 1,
    'configRoot' => 'kernel',
    'additionalKeys' => false,
    'keys' => [
        'boot' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'default_env' => [
                    'required' => true,
                    'type' => 'non-empty-string',
                ],
                'default_preset' => [
                    'required' => true,
                    'type' => 'non-empty-string',
                ],
                'default_debug' => [
                    'required' => true,
                    'type' => 'bool',
                ],
            ],
        ],
        'env' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'source_policy' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'default_local' => [
                            'required' => true,
                            'type' => 'non-empty-string',
                            'allowedValues' => [
                                'strict_dotenv',
                            ],
                        ],
                        'default_production' => [
                            'required' => true,
                            'type' => 'non-empty-string',
                            'allowedValues' => [
                                'allow_system',
                            ],
                        ],
                    ],
                ],
                'dotenv' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'files' => [
                            'required' => true,
                            'type' => 'list',
                            'items' => [
                                'type' => 'non-empty-string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'uow' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'attributes' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'max_depth' => [
                            'required' => true,
                            'type' => 'int',
                            'min' => 1,
                        ],
                        'max_keys' => [
                            'required' => true,
                            'type' => 'int',
                            'min' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
