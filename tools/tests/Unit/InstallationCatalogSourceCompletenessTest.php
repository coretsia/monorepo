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

namespace Coretsia\Tools\Tests\Unit;

use Coretsia\Tools\Support\ComposerJson;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;
use PHPUnit\Framework\TestCase;

final class InstallationCatalogSourceCompletenessTest extends TestCase
{
    public function testCatalogContainsExactlyMaterializedLayeredRuntimePackagesAndNoRepositoryPaths(): void
    {
        $repoRoot = \dirname(__DIR__, 3);
        $catalog = require $repoRoot . '/packages/core/kernel/resources/packaging/installation-catalog.php';

        self::assertIsArray($catalog);

        $catalogKeys = \array_keys($catalog);
        \sort($catalogKeys, \SORT_STRING);

        self::assertSame(
            [
                'modules',
                'publicConstraint',
                'releaseLine',
                'schemaVersion',
            ],
            $catalogKeys,
        );
        self::assertSame(1, $catalog['schemaVersion'] ?? null);
        self::assertIsString($catalog['releaseLine'] ?? null);
        self::assertIsString($catalog['publicConstraint'] ?? null);
        self::assertIsArray($catalog['modules'] ?? null);

        $repository = RepositoryContext::fromToolsRoot($repoRoot . '/tools');
        $workspace = WorkspacePackageCatalog::discover($repository);
        $expected = [];

        foreach ($workspace->layeredPackages() as $product) {
            $manifest = ComposerJson::readObject($product['composerJsonPath']);
            $extra = $manifest['extra'] ?? new \stdClass();

            if ($extra instanceof \stdClass) {
                $extra = [];
            } else {
                self::assertIsArray($extra);
                self::assertFalse(\array_is_list($extra));
            }

            $coretsia = $extra['coretsia'] ?? new \stdClass();

            if ($coretsia instanceof \stdClass) {
                $coretsia = [];
            } else {
                self::assertIsArray($coretsia);
                self::assertFalse(\array_is_list($coretsia));
            }

            if (\array_key_exists('kind', $coretsia)) {
                self::assertIsString($coretsia['kind']);
            }

            if (($coretsia['kind'] ?? null) !== 'runtime') {
                self::assertArrayNotHasKey('moduleId', $coretsia);

                continue;
            }

            self::assertIsString($coretsia['moduleId'] ?? null);

            $requires = $coretsia['requires'] ?? [];
            $conflicts = $coretsia['conflicts'] ?? [];

            self::assertIsArray($requires);
            self::assertTrue(\array_is_list($requires));
            self::assertIsArray($conflicts);
            self::assertTrue(\array_is_list($conflicts));

            foreach (
                [
                    ...$requires,
                    ...$conflicts,
                ] as $relatedModuleId
            ) {
                self::assertIsString($relatedModuleId);
            }

            \sort($requires, \SORT_STRING);
            \sort($conflicts, \SORT_STRING);

            $expected[$coretsia['moduleId']] = [
                'composerName' => $product['composerName'],
                'requires' => $requires,
                'conflicts' => $conflicts,
            ];
        }

        \ksort($expected, \SORT_STRING);

        $actual = [];

        foreach ($catalog['modules'] as $moduleId => $entry) {
            self::assertIsString($moduleId);
            self::assertIsArray($entry);

            $entryKeys = \array_keys($entry);
            \sort($entryKeys, \SORT_STRING);

            self::assertSame(
                [
                    'composerName',
                    'conflicts',
                    'requires',
                ],
                $entryKeys,
            );
            self::assertIsString($entry['composerName']);
            self::assertIsArray($entry['requires']);
            self::assertTrue(
                \array_is_list($entry['requires']),
            );
            self::assertIsArray($entry['conflicts']);
            self::assertTrue(
                \array_is_list($entry['conflicts']),
            );

            foreach (
                [
                    ...$entry['requires'],
                    ...$entry['conflicts'],
                ] as $relatedModuleId
            ) {
                self::assertIsString($relatedModuleId);
            }

            $actual[$moduleId] = [
                'composerName' => $entry['composerName'],
                'requires' => $entry['requires'],
                'conflicts' => $entry['conflicts'],
            ];

            self::assertStringNotContainsString('packages/', $entry['composerName']);
        }

        \ksort($actual, \SORT_STRING);

        self::assertSame($expected, $actual);
    }
}
