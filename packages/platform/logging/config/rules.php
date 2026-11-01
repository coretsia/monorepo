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
 * Declarative validation rules for the `logging` configuration subtree.
 *
 * Every key is mandatory and unknown keys (including reserved `@*` keys)
 * are rejected by the existing Kernel ConfigValidator. The stream destination
 * is fixed internally to php://stderr; `logging.stream` is not a valid key.
 * Neither driver nor format can disable mandatory shared redaction.
 *
 * The required `file` map contains only `path` and `name`. The declarative
 * string rule permits an empty path for inactive file mode; driver-dependent
 * absolute local directory validation belongs to LoggingServiceFactory, and
 * availability checks belong to StreamLogger at emission time.
 *
 * The existing `reset-group-id` type enforces a bounded ASCII grammar for
 * both the filename stem and service name: [a-z][a-z0-9_-]{0,63}.
 * `auto` is the only automatic service-name sentinel. No package-local
 * executable validator or rule type is introduced.
 *
 * The defaults file returns the subtree only:
 *
 *     config/logging.php => ['driver' => 'stream', ...]
 *
 * It must not return ['logging' => [...]].
 */
return [
    'schemaVersion' => 1,
    'configRoot' => 'logging',
    'additionalKeys' => false,
    'keys' => [
        'driver' => [
            'required' => true,
            'type' => 'string',
            'allowedValues' => [
                'stream',
                'file',
                'null',
            ],
        ],
        'file' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'path' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'name' => [
                    'required' => true,
                    'type' => 'reset-group-id',
                ],
            ],
        ],
        'level' => [
            'required' => true,
            'type' => 'string',
            'allowedValues' => [
                'debug',
                'info',
                'notice',
                'warning',
                'error',
                'critical',
                'alert',
                'emergency',
            ],
        ],
        'max_record_bytes' => [
            'required' => true,
            'type' => 'int',
            'min' => 1024,
            'max' => 65536,
        ],
        'service_name' => [
            'required' => true,
            'type' => 'reset-group-id',
        ],
        'format' => [
            'required' => true,
            'type' => 'string',
            'allowedValues' => [
                'jsonl',
                'logfmt',
            ],
        ],
    ],
];
