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
use Coretsia\Kernel\Module\ModePreset;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;

final class KernelRequiresFoundationInModulePlanTest extends TestCase
{
    public function testCoreKernelRequiresCoreFoundationInResolvedModulePlan(): void
    {
        $plan = self::resolver()->resolve(
            app: 'api',
            installed: self::manifest([
                self::descriptor(
                    'core.kernel',
                    requires: [
                        'core.foundation',
                    ],
                ),
                self::descriptor('core.foundation'),
            ]),
            preset: self::preset(
                required: [
                    'core.kernel',
                ],
            ),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
            ],
            self::moduleIdValues($plan->enabled()),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
            ],
            self::moduleIdValues($plan->topologicalOrder()),
        );

        self::assertSame([], self::moduleIdValues($plan->disabled()));
        self::assertSame([], self::moduleIdValues($plan->optionalMissing()));
        self::assertSame([], $plan->warnings());

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
            ],
            \array_keys($plan->modules()),
        );

        self::assertSame(
            [
                'app' => 'api',
                'disabled' => [],
                'enabled' => [
                    'core.foundation',
                    'core.kernel',
                ],
                'modules' => [
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
                ],
                'optionalMissing' => [],
                'preset' => 'micro',
                'schemaVersion' => 1,
                'topologicalOrder' => [
                    'core.foundation',
                    'core.kernel',
                ],
                'warnings' => [],
            ],
            $plan->toArray(),
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
     * @param list<string> $optional
     * @param list<string> $disabled
     */
    private static function preset(
        array $required,
        array $optional = [],
        array $disabled = [],
    ): ModePreset {
        return new ModePreset(
            schemaVersion: 1,
            name: 'micro',
            description: 'Micro test mode.',
            required: self::moduleIds($required),
            optional: self::moduleIds($optional),
            disabled: self::moduleIds($disabled),
            featureBundles: [],
            metadata: [],
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
