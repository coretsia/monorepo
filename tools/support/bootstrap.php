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

    $repoRoot = \realpath($bootstrapDir . '/../..');

    if (\is_string($repoRoot)) {
        $repoRoot = \str_replace('\\', '/', $repoRoot);

        if ($repoRoot !== '/' && \preg_match('~\A[A-Za-z]:/\z~', $repoRoot) !== 1) {
            $repoRoot = \rtrim($repoRoot, '/');
        }
    } else {
        $repoRoot = null;
    }

    if (
        $repoRoot !== null
        && (
            !\is_dir(\rtrim($repoRoot, '/') . '/packages')
            || !\is_dir(\rtrim($repoRoot, '/') . '/tools')
            || !\is_file(\rtrim($repoRoot, '/') . '/composer.json')
        )
    ) {
        $repoRoot = null;
    }

    if ($repoRoot !== null && !\defined('CORETSIA_REPO_ROOT')) {
        \define('CORETSIA_REPO_ROOT', $repoRoot);
    }

    if ($repoRoot !== null) {
        $autoloadPath = \rtrim($repoRoot, '/') . '/vendor/autoload.php';

        if (\is_file($autoloadPath) && \is_readable($autoloadPath)) {
            try {
                @require_once $autoloadPath;
                return;
            } catch (\Throwable) {
                // Fall through to the deterministic bootstrap failure below.
            }
        }
    }

    // Deterministic failure: do not leak absolute paths.
    $consolePath = $bootstrapDir . '/ConsoleOutput.php';
    if (\is_file($consolePath) && \is_readable($consolePath)) {
        try {
            @require_once $consolePath;

            if (\class_exists(\Coretsia\Tools\Support\ConsoleOutput::class, false)) {
                \Coretsia\Tools\Support\ConsoleOutput::codeWithDiagnostics(
                    'CORETSIA_TOOLS_BOOTSTRAP_AUTOLOAD_MISSING',
                    ['autoload-missing'],
                );
            }
        } catch (\Throwable) {
            // No safe output channel remains.
        }
    }

    exit(1);
})();
