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

use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use PHPUnit\Framework\TestCase;

final class ComposerManifestReaderSortsModulesDeterministicallyTest extends TestCase
{
    public function testManifestModulesAreSortedByModuleIdUsingByteOrder(): void
    {
        $manifest = self::readerForInstalledData(self::unsortedInstalledData())->read();

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
                'platform.http',
                'platform.routing',
            ],
            $manifest->ids(),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
                'platform.http',
                'platform.routing',
            ],
            \array_map(
                static fn (array $module): string => $module['moduleId'],
                $manifest->toArray()['modules'],
            ),
        );
    }

    public function testDependencyAndConflictMetadataListsAreSortedDeterministically(): void
    {
        $kernel = self::readerForInstalledData(self::unsortedInstalledData())
            ->read()
            ->get('core.kernel');

        self::assertNotNull($kernel);

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
    }

    public function testSortingDoesNotDependOnLocale(): void
    {
        $originalLocale = \setlocale(\LC_COLLATE, '0');

        try {
            $expected = null;

            foreach (self::availableCollationLocales() as $locale) {
                \setlocale(\LC_COLLATE, $locale);

                $actual = self::readerForInstalledData(self::unsortedInstalledData())
                    ->read()
                    ->toArray();

                if ($expected === null) {
                    $expected = $actual;

                    continue;
                }

                self::assertSame($expected, $actual);
            }

            self::assertNotNull($expected);
        } finally {
            if (\is_string($originalLocale)) {
                \setlocale(\LC_COLLATE, $originalLocale);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function unsortedInstalledData(): array
    {
        return [
            [
                'root' => [
                    'name' => 'coretsia/test-app',
                    'type' => 'project',
                    'extra' => [],
                ],
                'versions' => [
                    'coretsia/platform-routing' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'platform.routing',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                    'coretsia/core-kernel' => [
                        'type' => 'library',
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
                    'coretsia/platform-http' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'platform.http',
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
        ];
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

    /**
     * @return list<string>
     */
    private static function availableCollationLocales(): array
    {
        $candidates = [
            'C',
            'POSIX',
            'en_US.UTF-8',
            'de_DE.UTF-8',
            'uk_UA.UTF-8',
        ];

        $available = [];

        foreach ($candidates as $candidate) {
            $result = @\setlocale(\LC_COLLATE, $candidate);

            if (\is_string($result)) {
                $available[$result] = $result;
            }
        }

        $available['C'] = 'C';

        \ksort($available, \SORT_STRING);

        return \array_values($available);
    }
}
