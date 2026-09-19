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
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/GateRuntime.php';

exit(GateRuntime::execute(
    'CORETSIA_PACKAGE_PHPUNIT_CONFIG_FORBIDDEN',
    'CORETSIA_PACKAGE_PHPUNIT_CONFIG_GATE_FAILED',
    static function (RepositoryContext $repository): array {
        $catalog = WorkspacePackageCatalog::discover($repository);

        /** @var list<string> $violations */
        $violations = [];

        foreach ($catalog->all() as $product) {
            foreach (['phpunit.xml', 'phpunit.dist.xml'] as $fileName) {
                $path = $product['absolutePath'] . '/' . $fileName;

                if (!is_file($path)) {
                    continue;
                }

                $repository->resolveExistingFile($path);

                $violations[] = $repository->relativeToRepo($path) . ': forbidden-package-phpunit-config';
            }
        }

        return $violations;
    },
));
