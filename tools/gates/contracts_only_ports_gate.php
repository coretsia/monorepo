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
use Coretsia\Tools\Support\PhpSourceFinder;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/GateRuntime.php';

exit(GateRuntime::execute(
    'CORETSIA_CONTRACTS_ONLY_PORTS_FORBIDDEN',
    'CORETSIA_CONTRACTS_ONLY_PORTS_GATE_FAILED',
    static function (RepositoryContext $repository): array {
        $catalog = WorkspacePackageCatalog::discover($repository);
        $contractsOwner = $catalog->byPackageId('core/contracts');
        $contractsOwnerSrc = null;

        if ($contractsOwner !== null) {
            $candidate = $contractsOwner['absolutePath'] . '/src';

            if (is_dir($candidate)) {
                $contractsOwnerSrc = $repository->resolveExistingDirectory($candidate);
            }
        }

        $roots = [];

        foreach ($catalog->all() as $product) {
            $src = $product['absolutePath'] . '/src';

            if (!is_dir($src)) {
                continue;
            }

            $roots[] = $repository->resolveExistingDirectory($src);
        }

        $violations = [];

        foreach (PhpSourceFinder::findMany($roots, ['tests', 'fixtures', 'vendor']) as $absPath) {
            if (
                $contractsOwnerSrc !== null
                && RepositoryContext::containsPath($contractsOwnerSrc, $absPath)
            ) {
                continue;
            }

            $repoRelPath = $repository->relativeToRepo($absPath);
            $reason = coretsia_contracts_only_ports_gate_detect_violation_reason($repoRelPath);

            if ($reason === null) {
                continue;
            }

            $violations[] = $repoRelPath . ': ' . $reason;
        }

        return $violations;
    },
));

function coretsia_contracts_only_ports_gate_detect_violation_reason(string $repoRelPath): ?string
{
    $repoRelPath = \rtrim(\str_replace('\\', '/', $repoRelPath), '/');
    $basename = \basename($repoRelPath);

    if (\str_ends_with($basename, 'PortInterface.php')) {
        return 'forbidden-public-port-interface';
    }

    $pathForMatch = '/' . \ltrim($repoRelPath, '/');
    $srcMarker = '/src/';
    $srcPos = \strpos($pathForMatch, $srcMarker);

    if ($srcPos === false) {
        return null;
    }

    $insideSrc = \substr(
        $pathForMatch,
        $srcPos + \strlen($srcMarker),
    );

    if (\str_contains('/' . $insideSrc, '/Port/')) {
        return 'forbidden-public-port-namespace';
    }

    return null;
}
