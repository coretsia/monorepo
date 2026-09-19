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
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\ModePreset;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;

final class ModuleConflictsFailDeterministicallyTest extends TestCase
{
    public function testEnabledModuleConflictFailsWithSortedPairDiagnostics(): void
    {
        try {
            self::resolver()->resolve(
                app: 'api',
                installed: self::manifest([
                    self::descriptor(
                        'platform.http',
                        conflicts: [
                            'core.kernel',
                        ],
                    ),
                    self::descriptor('core.kernel'),
                ]),
                preset: self::preset(
                    required: [
                        'platform.http',
                        'core.kernel',
                    ],
                ),
            );

            self::fail('Expected enabled module conflict to fail deterministically.');
        } catch (ModuleConflictException $exception) {
            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_CONFLICT,
                $exception->errorCode(),
            );

            self::assertSame(
                ModuleConflictException::REASON_MODULE_CONFLICT,
                $exception->reason(),
            );

            self::assertSame(
                [
                    'higherModuleId' => 'platform.http',
                    'lowerModuleId' => 'core.kernel',
                ],
                $exception->context(),
            );

            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_CONFLICT
                . ': '
                . ModuleConflictException::REASON_MODULE_CONFLICT,
                $exception->getMessage(),
            );
        }
    }

    public function testMultipleConflictsFailWithSmallestCanonicalConflictKey(): void
    {
        try {
            self::resolver()->resolve(
                app: 'api',
                installed: self::manifest([
                    self::descriptor(
                        'platform.routing',
                        conflicts: [
                            'platform.http',
                            'core.kernel',
                        ],
                    ),
                    self::descriptor('platform.http'),
                    self::descriptor('core.kernel'),
                ]),
                preset: self::preset(
                    required: [
                        'platform.routing',
                        'platform.http',
                        'core.kernel',
                    ],
                ),
            );

            self::fail('Expected the smallest conflict candidate to fail deterministically.');
        } catch (ModuleConflictException $exception) {
            self::assertSame(
                ModuleConflictException::REASON_MODULE_CONFLICT,
                $exception->reason(),
            );

            self::assertSame(
                [
                    'higherModuleId' => 'platform.routing',
                    'lowerModuleId' => 'core.kernel',
                ],
                $exception->context(),
            );
        }
    }

    public function testRequiredDisabledModuleFailsAsConflictBeforeMissingRequiredModules(): void
    {
        try {
            self::resolver()->resolve(
                app: 'api',
                installed: self::manifest([
                    self::descriptor(
                        'core.kernel',
                        requires: [
                            'platform.http',
                        ],
                    ),
                ]),
                preset: self::preset(
                    required: [
                        'core.kernel',
                        'platform.metrics',
                    ],
                    disabled: [
                        'platform.http',
                    ],
                ),
            );

            self::fail('Expected disabled required dependency conflict to take graph precedence.');
        } catch (ModuleConflictException $exception) {
            self::assertSame(
                ModuleConflictException::REASON_REQUIRED_MODULE_DISABLED,
                $exception->reason(),
            );

            self::assertSame(
                [
                    'disabledModuleId' => 'platform.http',
                    'moduleId' => 'core.kernel',
                ],
                $exception->context(),
            );
        }
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
}
