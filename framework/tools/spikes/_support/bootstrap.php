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

(static function (): void {
    $bootstrapDir = __DIR__;

    $frameworkRoot = realpath($bootstrapDir . '/../../..');
    $frameworkRoot = \is_string($frameworkRoot)
        ? rtrim(str_replace('\\', '/', $frameworkRoot), '/')
        : null;

    $repoRoot = null;
    if ($frameworkRoot !== null) {
        $repoRootResolved = realpath($frameworkRoot . '/..');
        $repoRoot = \is_string($repoRootResolved)
            ? rtrim(str_replace('\\', '/', $repoRootResolved), '/')
            : null;
    }

    if ($frameworkRoot !== null && !\defined('CORETSIA_FRAMEWORK_ROOT')) {
        \define('CORETSIA_FRAMEWORK_ROOT', $frameworkRoot);
    }
    if ($repoRoot !== null && !\defined('CORETSIA_REPO_ROOT')) {
        \define('CORETSIA_REPO_ROOT', $repoRoot);
    }

    // Ordered single-choice autoload resolution (no probing beyond these two candidates).
    $candidates = [];

    if ($frameworkRoot !== null) {
        $candidates[] = $frameworkRoot . '/vendor/autoload.php';
    }
    if ($repoRoot !== null) {
        $candidates[] = $repoRoot . '/vendor/autoload.php';
    }

    foreach ($candidates as $autoloadPath) {
        if (\is_file($autoloadPath) && \is_readable($autoloadPath)) {
            require_once $autoloadPath;
            return;
        }
    }

    // Deterministic failure: do not leak absolute paths.
    $consolePath = $bootstrapDir . '/ConsoleOutput.php';
    if (\is_file($consolePath) && \is_readable($consolePath)) {
        require_once $consolePath;

        \Coretsia\Tools\Spikes\_support\ConsoleOutput::line('CORETSIA_SPIKES_BOOTSTRAP_AUTOLOAD_MISSING');
        \Coretsia\Tools\Spikes\_support\ConsoleOutput::line('autoload-missing');
    }

    exit(1);
})();
