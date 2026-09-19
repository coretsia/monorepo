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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use PHPUnit\Framework\TestCase;

final class ComposerManifestReaderReadsRequiresConflictsFromExtraCoretsiaTest extends TestCase
{
    public function testReadsRequiresAndConflictsOnlyFromExtraCoretsiaMetadata(): void
    {
        $manifest = self::readerForInstalledData([
            [
                'root' => [
                    'name' => 'coretsia/test-app',
                    'type' => 'project',
                    'extra' => [],
                ],
                'versions' => [
                    'coretsia/core-kernel' => [
                        'type' => 'library',

                        /*
                         * Composer package-level require/conflict are install
                         * constraints. ComposerManifestReader must ignore them
                         * for runtime module graph edges.
                         */
                        'require' => [
                            'acme/not-a-runtime-module' => '^1.0',
                        ],
                        'conflict' => [
                            'acme/not-a-runtime-conflict' => '*',
                        ],

                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.kernel',
                                'kind' => 'runtime',
                                'requires' => [
                                    'platform.cli',
                                    'core.foundation',
                                    'platform.cli',
                                ],
                                'conflicts' => [
                                    'platform.routing',
                                    'platform.http',
                                    'platform.routing',
                                ],
                            ],
                        ],
                    ],
                    'coretsia/core-foundation' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.foundation',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                    'coretsia/platform-cli' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'platform.cli',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                    'coretsia/platform-http' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'platform.http',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                    'coretsia/platform-routing' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'platform.routing',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                ],
            ],
        ])->read();

        $kernel = $manifest->get('core.kernel');

        self::assertInstanceOf(ModuleDescriptor::class, $kernel);

        self::assertSame(
            [
                'core.foundation',
                'platform.cli',
            ],
            $kernel->metadata()['requires'] ?? null,
        );

        self::assertSame(
            [
                'platform.http',
                'platform.routing',
            ],
            $kernel->metadata()['conflicts'] ?? null,
        );

        self::assertNotContains(
            'acme/not-a-runtime-module',
            $kernel->metadata()['requires'] ?? [],
        );

        self::assertNotContains(
            'acme/not-a-runtime-conflict',
            $kernel->metadata()['conflicts'] ?? [],
        );
    }

    public function testMissingRequiresAndConflictsAreExportedAsEmptyLists(): void
    {
        $manifest = self::readerForInstalledData([
            [
                'root' => [
                    'name' => 'coretsia/test-app',
                    'type' => 'project',
                    'extra' => [],
                ],
                'versions' => [
                    'coretsia/core-foundation' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.foundation',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                ],
            ],
        ])->read();

        $foundation = $manifest->get('core.foundation');

        self::assertInstanceOf(ModuleDescriptor::class, $foundation);

        self::assertSame(
            [
                'conflicts' => [],
                'requires' => [],
            ],
            $foundation->metadata(),
        );
    }

    public function testRequiresAndConflictsAreExportedInDescriptorArray(): void
    {
        $manifest = self::readerForInstalledData([
            [
                'root' => [
                    'name' => 'coretsia/test-app',
                    'type' => 'project',
                    'extra' => [],
                ],
                'versions' => [
                    'coretsia/core-kernel' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.kernel',
                                'kind' => 'runtime',
                                'requires' => [
                                    'platform.cli',
                                    'core.foundation',
                                ],
                                'conflicts' => [
                                    'platform.http',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])->read();

        self::assertSame(
            [
                'conflicts' => [
                    'platform.http',
                ],
                'requires' => [
                    'core.foundation',
                    'platform.cli',
                ],
            ],
            $manifest->get('core.kernel')?->toArray()['metadata'] ?? null,
        );
    }

    /**
     * @param list<array<string, mixed>> $installedData
     */
    private static function readerForInstalledData(array $installedData): ComposerManifestReader
    {
        return new ComposerManifestReader(
            new ComposerInstalledMetadataProvider($installedData),
        );
    }
}
