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
use Coretsia\Tools\Support\ComposerJson;
use Coretsia\Tools\Support\ConsoleOutput;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/ConsoleOutput.php';
require_once __DIR__ . '/../support/ErrorCodes.php';
require_once __DIR__ . '/../support/DeterministicException.php';
require_once __DIR__ . '/../support/DeterministicFile.php';
require_once __DIR__ . '/../support/RepositoryContext.php';
require_once __DIR__ . '/../support/ComposerJson.php';
require_once __DIR__ . '/../support/WorkspacePackageCatalog.php';

self_runTestingBootstrap(static function (): void {
    $repository = RepositoryContext::discoverFrom(__DIR__);
    $repoRoot = $repository->repoRoot();

    if (!\defined('CORETSIA_REPO_ROOT')) {
        \define('CORETSIA_REPO_ROOT', $repoRoot);
    }

    try {
        $autoloadPath = $repository->resolveExistingFile('vendor/autoload.php');
    } catch (Throwable) {
        throw new RuntimeException('autoload-missing');
    }

    if (!is_readable($autoloadPath)) {
        throw new RuntimeException('autoload-missing');
    }

    $loader = @require $autoloadPath;

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
        throw new RuntimeException('class-loader-unavailable');
    }

    // ---------------------------------------------------------------------
    // Tools tests PSR-4
    // ---------------------------------------------------------------------
    $toolsTestsCandidate = $repository->toolsRoot() . '/tests';

    if (is_dir($toolsTestsCandidate)) {
        $toolsTestsRoot = $repository->resolveExistingDirectory(
            $toolsTestsCandidate,
        );

        if ($repository->relativeToRepo($toolsTestsRoot) !== 'tools/tests') {
            throw new RuntimeException('tools-tests-path-invalid');
        }

        $classLoader->addPsr4(
            'Coretsia\\Tools\\Tests\\',
            [$toolsTestsRoot],
            true,
        );
    }

    // ---------------------------------------------------------------------
    // Package autoload-dev
    // ---------------------------------------------------------------------
    $catalog = WorkspacePackageCatalog::discover($repository);

    foreach ($catalog->all() as $product) {
        $data = ComposerJson::readObject($product['composerJsonPath']);

        $autoloadDev = $data['autoload-dev'] ?? null;
        if (!is_array($autoloadDev)) {
            continue;
        }

        $psr4 = $autoloadDev['psr-4'] ?? null;
        if (!is_array($psr4) || $psr4 === []) {
            continue;
        }

        $pkgDir = $product['absolutePath'];

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

                $rel = trim(str_replace('\\', '/', $rel));

                if (
                    $rel === ''
                    || str_contains($rel, "\0")
                    || RepositoryContext::isAbsolutePath($rel)
                ) {
                    continue;
                }

                $candidate = $pkgDir . '/' . trim($rel, '/');

                if (!is_dir($candidate)) {
                    continue;
                }

                $abs = $repository->resolveExistingDirectory($candidate);

                if (!RepositoryContext::containsPath($pkgDir, $abs)) {
                    continue;
                }

                $absPaths[] = $abs;
            }

            if ($absPaths === []) {
                continue;
            }

            $classLoader->addPsr4($prefix, $absPaths, true);
        }
    }
});

function self_runTestingBootstrap(callable $bootstrap): void
{
    try {
        $bootstrap();
    } catch (Throwable $exception) {
        ConsoleOutput::codeWithDiagnostics(
            'CORETSIA_TEST_BOOTSTRAP_FAILED',
            [$exception->getMessage()],
        );

        exit(1);
    }
}
