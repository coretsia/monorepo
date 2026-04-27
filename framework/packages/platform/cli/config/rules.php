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

return [
    'schemaVersion' => 1,
    'configRoot' => 'cli',
    'additionalKeys' => false,
    'keys' => [
        'commands' => [
            'required' => true,
            'type' => 'list',
            'items' => [
                'type' => 'non-empty-string-no-ws',
            ],
        ],
        'output' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'format' => [
                    'required' => true,
                    'type' => 'string',
                    'allowedValues' => [
                        'json',
                        'text',
                    ],
                ],
                'redaction' => [
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
