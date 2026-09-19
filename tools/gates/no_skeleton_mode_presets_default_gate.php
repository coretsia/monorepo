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
    'CORETSIA_NO_SKELETON_MODE_PRESETS_DEFAULT_FORBIDDEN',
    'CORETSIA_NO_SKELETON_MODE_PRESETS_DEFAULT_GATE_FAILED',
    static function (RepositoryContext $repository): array {
        return coretsia_no_skeleton_mode_presets_default_gate_scan($repository);
    },
));

/**
 * @return list<string>
 */
function coretsia_no_skeleton_mode_presets_default_gate_scan(
    RepositoryContext $repository,
): array {
    $modesDir = $repository->resolve('packages/applications/skeleton/config/modes');

    if (!is_dir($modesDir)) {
        return [];
    }

    $repository->resolveExistingDirectory($modesDir);

    $entries = GateRuntime::withSuppressedErrors(
        static fn (): array|false => scandir($modesDir),
    );

    if ($entries === false) {
        throw new RuntimeException('modes-dir-scan-failed');
    }

    /** @var list<string> $violations */
    $violations = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $modesDir . '/' . $entry;

        if (!is_file($path) || !str_ends_with($entry, '.php')) {
            continue;
        }

        $repository->resolveExistingFile($path);

        $violations[] = $repository->relativeToRepo($path) . ': forbidden-default-mode-preset-config';
    }

    return $violations;
}
