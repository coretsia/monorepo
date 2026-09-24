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
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;

final class ModuleGraphResolverAddsTransitiveRequiredDependenciesTest extends TestCase
{
    public function testEnabledModuleRequiresInstalledModuleTransitively(): void
    {
        $plan = self::resolver()->resolve(
            app: 'api',
            installed: self::manifest([
                self::descriptor(
                    'platform.cli',
                    requires: [
                        'core.kernel',
                    ],
                ),
                self::descriptor(
                    'core.kernel',
                    requires: [
                        'core.foundation',
                    ],
                ),
                self::descriptor('core.foundation'),
                self::descriptor('platform.http'),
            ]),
            selection: self::selection(
                required: [
                    'platform.cli',
                ],
            ),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            self::moduleIdValues($plan->enabled()),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            self::moduleIdValues($plan->topologicalOrder()),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            \array_keys($plan->modules()),
        );

        self::assertArrayNotHasKey('platform.http', $plan->modules());

        self::assertSame(
            [
                'core.foundation' => [
                    'composerName' => 'coretsia/core-foundation',
                    'conflicts' => [],
                    'moduleId' => 'core.foundation',
                    'requires' => [],
                ],
                'core.kernel' => [
                    'composerName' => 'coretsia/core-kernel',
                    'conflicts' => [],
                    'moduleId' => 'core.kernel',
                    'requires' => [
                        'core.foundation',
                    ],
                ],
                'platform.cli' => [
                    'composerName' => 'coretsia/platform-cli',
                    'conflicts' => [],
                    'moduleId' => 'platform.cli',
                    'requires' => [
                        'core.kernel',
                    ],
                ],
            ],
            $plan->toArray()['modules'],
        );
    }

    public function testEquivalentManifestAndPresetPermutationsProduceIdenticalModulePlan(): void
    {
        $first = self::resolver()->resolve(
            app: 'api',
            installed: self::manifest([
                self::descriptor(
                    'platform.http',
                    requires: [
                        'core.kernel',
                    ],
                ),
                self::descriptor(
                    'core.kernel',
                    requires: [
                        'core.foundation',
                    ],
                ),
                self::descriptor(
                    'platform.cli',
                    requires: [
                        'core.foundation',
                    ],
                ),
                self::descriptor('core.foundation'),
            ]),
            selection: self::selection(
                required: [
                    'platform.http',
                    'platform.cli',
                ],
            ),
        );

        $second = self::resolver()->resolve(
            app: 'api',
            installed: self::manifest([
                self::descriptor('core.foundation'),
                self::descriptor(
                    'platform.cli',
                    requires: [
                        'core.foundation',
                    ],
                ),
                self::descriptor(
                    'platform.http',
                    requires: [
                        'core.kernel',
                    ],
                ),
                self::descriptor(
                    'core.kernel',
                    requires: [
                        'core.foundation',
                    ],
                ),
            ]),
            selection: self::selection(
                required: [
                    'platform.cli',
                    'platform.http',
                ],
            ),
        );

        $third = self::resolver()->resolve(
            app: 'api',
            installed: self::manifest([
                self::descriptor(
                    'core.kernel',
                    requires: [
                        'core.foundation',
                    ],
                ),
                self::descriptor(
                    'platform.http',
                    requires: [
                        'core.kernel',
                    ],
                ),
                self::descriptor('core.foundation'),
                self::descriptor(
                    'platform.cli',
                    requires: [
                        'core.foundation',
                    ],
                ),
            ]),
            selection: self::selection(
                required: [
                    'platform.http',
                    'platform.cli',
                ],
            ),
        );

        self::assertSame($first->toArray(), $second->toArray());
        self::assertSame($first->toArray(), $third->toArray());

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
                'platform.http',
            ],
            self::moduleIdValues($first->topologicalOrder()),
        );
    }

    public function testSelectedNonRequiredModuleAlsoExpandsRequiredDependencyClosure(): void
    {
        $plan = self::resolver()->resolve(
            app: 'api',
            installed: self::manifest([
                self::descriptor(
                    'platform.http',
                    requires: [
                        'core.kernel',
                    ],
                ),
                self::descriptor(
                    'core.kernel',
                    requires: [
                        'core.foundation',
                    ],
                ),
                self::descriptor('core.foundation'),
            ]),
            selection: self::selection(
                required: [],
                modules: [
                    'platform.http',
                ],
            ),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.http',
            ],
            self::moduleIdValues($plan->enabled()),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.http',
            ],
            self::moduleIdValues($plan->topologicalOrder()),
        );
    }

    private static function resolver(): ModuleGraphResolver
    {
        return new ModuleGraphResolver(new TopologicalSorter());
    }

    /**
     * @param list<ModuleDescriptor> $modules
     */
    private static function manifest(array $modules): ModuleManifest
    {
        return new ModuleManifest($modules);
    }

    /**
     * @param list<string> $requires
     * @param list<string> $conflicts
     */
    private static function descriptor(
        string $moduleId,
        array $requires = [],
        array $conflicts = [],
    ): ModuleDescriptor {
        return new ModuleDescriptor(
            id: ModuleId::fromString($moduleId),
            composerName: self::composerName($moduleId),
            packageKind: 'runtime',
            moduleClass: null,
            capabilities: [],
            metadata: [
                'conflicts' => self::sortedUniqueStrings($conflicts),
                'requires' => self::sortedUniqueStrings($requires),
            ],
        );
    }

    /**
     * @param list<string> $required
     * @param list<string> $modules
     * @param list<string> $excluded
     */
    private static function selection(
        array $required,
        array $modules = [],
        array $excluded = [],
    ): ModuleSelection {
        return new ModuleSelection(
            roots: self::moduleIds(self::sortedUniqueStrings([...$required, ...$modules])),
            excluded: self::moduleIds(self::sortedUniqueStrings($excluded)),
        );
    }

    private static function composerName(string $moduleId): string
    {
        return 'coretsia/' . \str_replace('.', '-', $moduleId);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sortedUniqueStrings(array $values): array
    {
        $values = \array_values(\array_unique($values));

        \usort($values, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $values;
    }

    /**
     * @param list<string> $values
     *
     * @return list<ModuleId>
     */
    private static function moduleIds(array $values): array
    {
        return \array_map(
            static fn (string $value): ModuleId => ModuleId::fromString($value),
            $values,
        );
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdValues(array $moduleIds): array
    {
        return \array_map(
            static fn (ModuleId $moduleId): string => $moduleId->value(),
            $moduleIds,
        );
    }
}
