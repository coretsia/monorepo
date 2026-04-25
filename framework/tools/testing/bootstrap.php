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

use Composer\Autoload\ClassLoader;

(static function (): void {
    // tools/testing -> framework
    $frameworkRoot = dirname(__DIR__, 2);
    $frameworkRoot = rtrim(str_replace('\\', '/', $frameworkRoot), '/');

    $repoRoot = dirname($frameworkRoot);
    $repoRoot = rtrim(str_replace('\\', '/', $repoRoot), '/');

    if (!\defined('CORETSIA_FRAMEWORK_ROOT')) {
        \define('CORETSIA_FRAMEWORK_ROOT', $frameworkRoot);
    }
    if (!\defined('CORETSIA_REPO_ROOT')) {
        \define('CORETSIA_REPO_ROOT', $repoRoot);
    }

    $autoloadPath = $frameworkRoot . '/vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        throw new RuntimeException('CORETSIA_TEST_BOOTSTRAP_FAILED: missing framework/vendor/autoload.php');
    }

    $loader = require $autoloadPath;

    $classLoader = null;

    if ($loader instanceof ClassLoader) {
        $classLoader = $loader;
    } elseif (class_exists(ClassLoader::class)) {
        /** @var array<string, ClassLoader> $loaders */
        $loaders = ClassLoader::getRegisteredLoaders();
        $keys = array_keys($loaders);
        sort($keys, SORT_STRING);

        foreach ($keys as $k) {
            if ($loaders[$k] instanceof ClassLoader) {
                $classLoader = $loaders[$k];
                break;
            }
        }
    }

    if (!$classLoader instanceof ClassLoader) {
        throw new RuntimeException('CORETSIA_TEST_BOOTSTRAP_FAILED: cannot obtain Composer ClassLoader');
    }

    // ---------------------------------------------------------------------
    // Tools tests PSR-4
    // ---------------------------------------------------------------------
    $toolsTestingTestsRoot = $frameworkRoot . '/tools/testing/tests';
    if (is_dir($toolsTestingTestsRoot)) {
        $classLoader->addPsr4('Coretsia\\Tools\\Testing\\Tests\\', [$toolsTestingTestsRoot], true);
    }

    $toolsTestsRoot = $frameworkRoot . '/tools/tests';
    if (is_dir($toolsTestsRoot)) {
        $classLoader->addPsr4('Coretsia\\Tools\\Tests\\', [$toolsTestsRoot], true);
    }

    // ---------------------------------------------------------------------
    // Package autoload-dev (future-safe, noop if no packages yet)
    // ---------------------------------------------------------------------
    $packagesRoot = $frameworkRoot . '/packages';
    if (!is_dir($packagesRoot)) {
        return;
    }

    $glob = $packagesRoot . '/*/*/composer.json';

    /** @var list<string> $composerFiles */
    $composerFiles = glob($glob);
    if ($composerFiles === false) {
        $composerFiles = [];
    }

    $composerFiles = array_values(array_filter($composerFiles, static fn (string $p): bool => $p !== ''));
    sort($composerFiles, SORT_STRING);

    foreach ($composerFiles as $composerJsonPath) {
        $composerJsonPath = str_replace('\\', '/', $composerJsonPath);

        $raw = @file_get_contents($composerJsonPath);
        if (!is_string($raw) || $raw === '') {
            continue;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            continue;
        }

        $autoloadDev = $data['autoload-dev'] ?? null;
        if (!is_array($autoloadDev)) {
            continue;
        }

        $psr4 = $autoloadDev['psr-4'] ?? null;
        if (!is_array($psr4) || $psr4 === []) {
            continue;
        }

        $pkgDir = rtrim(str_replace('\\', '/', dirname($composerJsonPath)), '/');

        foreach ($psr4 as $prefix => $paths) {
            if (!is_string($prefix) || trim($prefix) === '') {
                continue;
            }

            $pathList = [];
            if (is_string($paths)) {
                $pathList = [$paths];
            } elseif (is_array($paths)) {
                $pathList = $paths;
            } else {
                continue;
            }

            $absPaths = [];
            foreach ($pathList as $rel) {
                if (!is_string($rel)) {
                    continue;
                }

                $rel = trim(str_replace(["\0", '\\'], ['', '/'], $rel));
                if ($rel === '') {
                    continue;
                }

                $abs = $pkgDir . '/' . trim($rel, '/');
                $abs = rtrim(str_replace('\\', '/', $abs), '/');

                if (is_dir($abs)) {
                    $absPaths[] = $abs;
                }
            }

            if ($absPaths === []) {
                continue;
            }

            $classLoader->addPsr4($prefix, $absPaths, true);
        }
    }
})();
