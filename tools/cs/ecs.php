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

use Coretsia\Tools\Support\RepositoryContext;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

require_once __DIR__ . '/../support/RepositoryContext.php';

$repository = RepositoryContext::discoverFrom(__DIR__);
$packagesRoot = $repository->packagesRoot();
$toolsRoot = $repository->toolsRoot();

return ECSConfig::configure()
    ->withPaths([
        $packagesRoot,
        $toolsRoot,
    ])
    ->withFileExtensions([
        'php',
    ])
    ->withPreparedSets(
        psr12: true,
        namespaces: true,
    )
    ->withSpacing(
        indentation: Option::INDENTATION_SPACES,
        lineEnding: "\n",
    )
    ->withSkip([
        $packagesRoot . '/**/vendor/*',
        $packagesRoot . '/**/var/*',
        $packagesRoot . '/**/tests/fixtures/*',
        $packagesRoot . '/**/tests/fixtures/**/*',
        $packagesRoot . '/**/tests/Fixtures/*',
        $packagesRoot . '/**/tests/Fixtures/**/*',

        $toolsRoot . '/tests/*/fixtures/*',
        $toolsRoot . '/tests/*/fixtures/**/*',
        $toolsRoot . '/tests/Fixtures/*',
        $toolsRoot . '/tests/Fixtures/**/*',
    ]);
