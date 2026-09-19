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

use Coretsia\Tools\Support\GateRuntime;
use Coretsia\Tools\Support\RepositoryContext;

require_once __DIR__ . '/../support/GateRuntime.php';

exit(GateRuntime::execute(
    'CORETSIA_NO_SKELETON_BUNDLES_DEFAULT_FORBIDDEN',
    'CORETSIA_NO_SKELETON_BUNDLES_DEFAULT_GATE_FAILED',
    static function (RepositoryContext $repository): array {
        return coretsia_no_skeleton_bundles_default_gate_scan($repository);
    },
));

/**
 * @return list<string>
 */
function coretsia_no_skeleton_bundles_default_gate_scan(
    RepositoryContext $repository,
): array {
    $bundlesDir = $repository->resolve('packages/applications/skeleton/config/bundles');

    if (!is_dir($bundlesDir)) {
        return [];
    }

    $repository->resolveExistingDirectory($bundlesDir);

    $entries = GateRuntime::withSuppressedErrors(
        static fn (): array|false => scandir($bundlesDir),
    );

    if ($entries === false) {
        throw new RuntimeException('bundles-dir-scan-failed');
    }

    /** @var list<string> $violations */
    $violations = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $bundlesDir . '/' . $entry;

        if (!is_file($path) || !str_ends_with($entry, '.php')) {
            continue;
        }

        $repository->resolveExistingFile($path);

        $violations[] = $repository->relativeToRepo($path) . ': forbidden-default-bundle-config';
    }

    return $violations;
}
