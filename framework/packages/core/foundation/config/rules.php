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
 * Validation rules for the `foundation` configuration subtree.
 *
 * These rules intentionally keep the Phase 1 Foundation config strict:
 * - the file validates the `foundation` root subtree;
 * - unknown keys are rejected at every declared map level;
 * - reserved `@*` keys are rejected by the same strict-shape policy;
 * - tag discovery and reset orchestration are not feature-flagged;
 * - `foundation.reset.tag` is configurable but must be a non-empty string
 *   without whitespace.
 *
 * The defaults file must return the subtree only:
 *
 *     config/foundation.php => ['container' => [...], 'reset' => [...]]
 *
 * It must not return:
 *
 *     ['foundation' => [...]]
 */
return [
    'schemaVersion' => 1,
    'configRoot' => 'foundation',
    'additionalKeys' => false,
    'keys' => [
        'container' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'autowire_concrete' => [
                    'required' => true,
                    'type' => 'bool',
                ],
                'allow_reflection_for_concrete' => [
                    'required' => true,
                    'type' => 'bool',
                ],
            ],
        ],
        'reset' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'tag' => [
                    'required' => true,
                    'type' => 'non-empty-string-no-ws',
                ],
            ],
        ],
    ],
];
