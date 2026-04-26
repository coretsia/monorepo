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

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

$frameworkRoot = dirname(__DIR__, 2);
$repoRoot = dirname($frameworkRoot);

return ECSConfig::configure()
    ->withPaths([
        $frameworkRoot . '/packages',
        $frameworkRoot . '/tools',
        $repoRoot . '/skeleton',
    ])
    ->withFileExtensions([
        'php',
    ])
    ->withPreparedSets(
        psr12: true,
    )
    ->withRules([
        NoUnusedImportsFixer::class,
    ])
    ->withSpacing(
        indentation: Option::INDENTATION_SPACES,
        lineEnding: "\n",
    )
    ->withSkip([
        $repoRoot . '/vendor/*',
        $repoRoot . '/skeleton/vendor/*',
        $repoRoot . '/skeleton/var/*',

        $frameworkRoot . '/vendor/*',
        $frameworkRoot . '/var/*',

        $frameworkRoot . '/packages/*/*/vendor/*',
        $frameworkRoot . '/packages/*/*/var/*',
        $frameworkRoot . '/packages/*/*/tests/fixtures/*',
        $frameworkRoot . '/packages/*/*/tests/fixtures/**/*',

        $frameworkRoot . '/tools/spikes/*/fixtures/*',
        $frameworkRoot . '/tools/spikes/*/fixtures/**/*',
        $frameworkRoot . '/tools/tests/*/fixtures/*',
        $frameworkRoot . '/tools/tests/*/fixtures/**/*',

        $repoRoot . '/docs/generated/*',
    ]);
