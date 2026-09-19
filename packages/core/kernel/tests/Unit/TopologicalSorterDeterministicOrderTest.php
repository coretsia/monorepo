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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;

final class TopologicalSorterDeterministicOrderTest extends TestCase
{
    public function testSortsDependenciesBeforeDependentsAndUsesLowestAvailableModuleId(): void
    {
        $sorter = new TopologicalSorter();

        $order = $sorter->sort([
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.http'),
                composerName: 'coretsia/platform-http',
                requires: [
                    self::moduleId('core.foundation'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('core.kernel'),
                composerName: 'coretsia/core-kernel',
                requires: [
                    self::moduleId('platform.cli'),
                    self::moduleId('core.foundation'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.routing'),
                composerName: 'coretsia/platform-routing',
                requires: [
                    self::moduleId('platform.http'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.cli'),
                composerName: 'coretsia/platform-cli',
                requires: [
                    self::moduleId('core.foundation'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('core.foundation'),
                composerName: 'coretsia/core-foundation',
            ),
        ]);

        self::assertSame(
            [
                'core.foundation',
                'platform.cli',
                'core.kernel',
                'platform.http',
                'platform.routing',
            ],
            self::moduleIdsToStrings($order),
        );
    }

    public function testSortOrderDoesNotDependOnInputOrder(): void
    {
        $firstOrder = self::moduleIdsToStrings(
            new TopologicalSorter()->sort(self::entriesInFirstOrder()),
        );

        $secondOrder = self::moduleIdsToStrings(
            new TopologicalSorter()->sort(self::entriesInSecondOrder()),
        );

        self::assertSame($firstOrder, $secondOrder);
        self::assertSame(
            [
                'core.foundation',
                'platform.cli',
                'core.kernel',
                'platform.http',
                'platform.routing',
            ],
            $firstOrder,
        );
    }

    public function testSortOrderDoesNotDependOnLocale(): void
    {
        $originalLocale = \setlocale(\LC_COLLATE, '0');

        try {
            $expected = null;

            foreach (self::availableCollationLocales() as $locale) {
                \setlocale(\LC_COLLATE, $locale);

                $actual = self::moduleIdsToStrings(
                    new TopologicalSorter()->sort(self::entriesInFirstOrder()),
                );

                if ($expected === null) {
                    $expected = $actual;

                    continue;
                }

                self::assertSame($expected, $actual);
            }

            self::assertSame(
                [
                    'core.foundation',
                    'platform.cli',
                    'core.kernel',
                    'platform.http',
                    'platform.routing',
                ],
                $expected,
            );
        } finally {
            if (\is_string($originalLocale)) {
                \setlocale(\LC_COLLATE, $originalLocale);
            }
        }
    }

    public function testEdgesToModulesOutsideEnabledSetAreIgnoredBySorter(): void
    {
        $sorter = new TopologicalSorter();

        $order = $sorter->sort([
            new ModulePlanEntry(
                moduleId: self::moduleId('core.kernel'),
                composerName: 'coretsia/core-kernel',
                requires: [
                    self::moduleId('platform.http'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('core.foundation'),
                composerName: 'coretsia/core-foundation',
            ),
        ]);

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
            ],
            self::moduleIdsToStrings($order),
        );
    }

    /**
     * @return list<ModulePlanEntry>
     */
    private static function entriesInFirstOrder(): array
    {
        return [
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.http'),
                composerName: 'coretsia/platform-http',
                requires: [
                    self::moduleId('core.foundation'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('core.kernel'),
                composerName: 'coretsia/core-kernel',
                requires: [
                    self::moduleId('platform.cli'),
                    self::moduleId('core.foundation'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.routing'),
                composerName: 'coretsia/platform-routing',
                requires: [
                    self::moduleId('platform.http'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.cli'),
                composerName: 'coretsia/platform-cli',
                requires: [
                    self::moduleId('core.foundation'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('core.foundation'),
                composerName: 'coretsia/core-foundation',
            ),
        ];
    }

    /**
     * @return list<ModulePlanEntry>
     */
    private static function entriesInSecondOrder(): array
    {
        return [
            new ModulePlanEntry(
                moduleId: self::moduleId('core.kernel'),
                composerName: 'coretsia/core-kernel',
                requires: [
                    self::moduleId('core.foundation'),
                    self::moduleId('platform.cli'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('core.foundation'),
                composerName: 'coretsia/core-foundation',
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.routing'),
                composerName: 'coretsia/platform-routing',
                requires: [
                    self::moduleId('platform.http'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.cli'),
                composerName: 'coretsia/platform-cli',
                requires: [
                    self::moduleId('core.foundation'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.http'),
                composerName: 'coretsia/platform-http',
                requires: [
                    self::moduleId('core.foundation'),
                ],
            ),
        ];
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

    private static function moduleId(string $value): ModuleId
    {
        return ModuleId::fromString($value);
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdsToStrings(array $moduleIds): array
    {
        return \array_map(
            static fn (ModuleId $moduleId): string => $moduleId->value(),
            $moduleIds,
        );
    }
}
