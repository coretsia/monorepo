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

use Coretsia\Contracts\Module\ModePresetInterface;
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\ModePreset;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;

final class ModuleGraphResolverFailsWhenEnabledModuleRequiresDisabledModuleTest extends TestCase
{
    public function testEnabledModuleRequiresDisabledModuleFailsAsConflict(): void
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
                    self::descriptor('platform.http'),
                ]),
                preset: self::preset(
                    required: [
                        'core.kernel',
                    ],
                    disabled: [
                        'platform.http',
                    ],
                ),
            );

            self::fail('Expected enabled module requiring disabled module to fail deterministically.');
        } catch (ModuleConflictException $exception) {
            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_CONFLICT,
                $exception->errorCode(),
            );

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

            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_CONFLICT
                . ': '
                . ModuleConflictException::REASON_REQUIRED_MODULE_DISABLED,
                $exception->getMessage(),
            );
        }
    }

    public function testPresetRequiredDisabledModuleFailsAsConflict(): void
    {
        try {
            self::resolver()->resolve(
                app: 'api',
                installed: self::manifest([
                    self::descriptor('core.kernel'),
                ]),
                preset: self::unsafePreset(
                    required: [
                        'core.kernel',
                    ],
                    disabled: [
                        'core.kernel',
                    ],
                ),
            );

            self::fail('Expected preset-required disabled module to fail deterministically.');
        } catch (ModuleConflictException $exception) {
            self::assertSame(
                ModuleConflictException::REASON_REQUIRED_MODULE_DISABLED,
                $exception->reason(),
            );

            self::assertSame(
                [
                    'disabledModuleId' => 'core.kernel',
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

    /**
     * Test-only preset double for graph-resolver robustness tests.
     *
     * This bypasses ModePreset's canonical disjoint-set invariant intentionally.
     *
     * @param list<string> $required
     * @param list<string> $optional
     * @param list<string> $disabled
     */
    private static function unsafePreset(
        array $required,
        array $optional = [],
        array $disabled = [],
    ): ModePresetInterface {
        return new class(self::moduleIds($required), self::moduleIds($optional), self::moduleIds($disabled), ) implements ModePresetInterface {
            /**
             * @param list<ModuleId> $required
             * @param list<ModuleId> $optional
             * @param list<ModuleId> $disabled
             */
            public function __construct(
                private readonly array $required,
                private readonly array $optional,
                private readonly array $disabled,
            ) {
            }

            public function schemaVersion(): int
            {
                return 1;
            }

            public function name(): string
            {
                return 'micro';
            }

            public function description(): ?string
            {
                return 'Micro test mode.';
            }

            /**
             * @return list<ModuleId>
             */
            public function required(): array
            {
                return $this->required;
            }

            /**
             * @return list<ModuleId>
             */
            public function optional(): array
            {
                return $this->optional;
            }

            /**
             * @return list<ModuleId>
             */
            public function disabled(): array
            {
                return $this->disabled;
            }

            /**
             * @return list<ModuleId>
             */
            public function moduleIds(): array
            {
                $enabled = [];

                foreach ($this->required as $moduleId) {
                    $enabled[$moduleId->value()] = $moduleId;
                }

                foreach ($this->optional as $moduleId) {
                    $enabled[$moduleId->value()] = $moduleId;
                }

                foreach ($this->disabled as $moduleId) {
                    unset($enabled[$moduleId->value()]);
                }

                \ksort($enabled, \SORT_STRING);

                return \array_values($enabled);
            }

            /**
             * @return array<string, mixed>
             */
            public function featureBundles(): array
            {
                return [];
            }

            /**
             * @return array<string, mixed>
             */
            public function metadata(): array
            {
                return [];
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return [
                    'schemaVersion' => 1,
                    'name' => 'micro',
                    'description' => 'Micro test mode.',
                    'required' => self::moduleIdValues($this->required),
                    'optional' => self::moduleIdValues($this->optional),
                    'disabled' => self::moduleIdValues($this->disabled),
                    'featureBundles' => [],
                    'metadata' => [],
                ];
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
        };
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
