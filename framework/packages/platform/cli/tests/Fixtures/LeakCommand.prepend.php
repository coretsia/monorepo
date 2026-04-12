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

// tests/Fixtures -> tests -> cli -> platform -> packages -> framework
$frameworkRoot = \dirname(__DIR__, 5);
$repoRoot = \dirname($frameworkRoot);

$autoloadCandidates = [
    $frameworkRoot . '/vendor/autoload.php',
    $repoRoot . '/vendor/autoload.php',
];

$autoload = null;

foreach ($autoloadCandidates as $candidate) {
    if (\is_file($candidate) && \is_readable($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === null) {
    // No output; deterministic hard-fail.
    exit(255);
}

require_once $autoload;

// Now contracts are autoloadable -> safe to load fixture command.
require_once __DIR__ . '/LeakCommand.php';
