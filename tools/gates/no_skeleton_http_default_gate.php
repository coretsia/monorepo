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
    'CORETSIA_NO_SKELETON_HTTP_DEFAULT_FORBIDDEN',
    'CORETSIA_NO_SKELETON_HTTP_DEFAULT_GATE_FAILED',
    static function (RepositoryContext $repository): array {
        $forbiddenFile = $repository->resolve('packages/applications/skeleton/config/http.php');

        if (!is_file($forbiddenFile)) {
            return [];
        }

        $repository->resolveExistingFile($forbiddenFile);

        return [
            $repository->relativeToRepo($forbiddenFile) . ': forbidden-default-http-config',
        ];
    },
));
