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
 * - `kernel.uow.attributes.max_depth` must be an integer greater than zero;
 * - `kernel.uow.attributes.max_keys` must be an integer greater than zero;
 * - this epic introduces no runtime feature flags, reset tag constants, HTTP
 *   adapter config, CLI adapter config, artifact config, or outcome mapping
 *   config.
 *
 * The defaults file must return the subtree only:
 *
 *     config/kernel.php => ['uow' => [...]]
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
