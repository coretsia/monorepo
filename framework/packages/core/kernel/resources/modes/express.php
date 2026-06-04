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
    'name' => 'express',
    'description' => 'Express web application mode.',
    'required' => [
        'core.foundation',
        'core.kernel',
        'platform.cli',
    ],
    'optional' => [
        'platform.http',
        'platform.logging',
        'platform.metrics',
        'platform.tracing',
    ],
    'disabled' => [],
    'featureBundles' => [
        'observability' => 'minimal',
    ],
    'metadata' => [],
];
