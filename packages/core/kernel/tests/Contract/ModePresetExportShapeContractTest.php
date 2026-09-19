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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModePreset;
use PHPUnit\Framework\TestCase;

final class ModePresetExportShapeContractTest extends TestCase
{
    public function testModePresetExportsStableShapeAndSortedModuleSets(): void
    {
        $preset = new ModePreset(
            schemaVersion: 1,
            name: 'hybrid',
            description: 'Hybrid web application mode.',
            required: [
                self::moduleId('platform.cli'),
                self::moduleId('core.kernel'),
                self::moduleId('core.foundation'),
            ],
            optional: [
                self::moduleId('platform.tracing'),
                self::moduleId('platform.logging'),
            ],
            disabled: [
                self::moduleId('platform.http'),
            ],
            featureBundles: [
                'profile' => [
                    'name' => 'hybrid',
                    'level' => 'standard',
                ],
                'observability' => 'minimal',
            ],
            metadata: [
                'stage' => 'contract',
                'owner' => [
                    'package' => 'core.kernel',
                    'type' => 'runtime',
                ],
            ],
        );

        self::assertSame(1, $preset->schemaVersion());
        self::assertSame('hybrid', $preset->name());
        self::assertSame('Hybrid web application mode.', $preset->description());

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            self::moduleIdsToStrings($preset->required()),
        );

        self::assertSame(
            [
                'platform.logging',
                'platform.tracing',
            ],
            self::moduleIdsToStrings($preset->optional()),
        );

        self::assertSame(
            [
                'platform.http',
            ],
            self::moduleIdsToStrings($preset->disabled()),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
                'platform.logging',
                'platform.tracing',
            ],
            self::moduleIdsToStrings($preset->moduleIds()),
        );

        $payload = $preset->toArray();

        self::assertSame(
            [
                'schemaVersion',
                'name',
                'description',
                'required',
                'optional',
                'disabled',
                'featureBundles',
                'metadata',
            ],
            \array_keys($payload),
        );

        self::assertSame(
            [
                'schemaVersion' => 1,
                'name' => 'hybrid',
                'description' => 'Hybrid web application mode.',
                'required' => [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                ],
                'optional' => [
                    'platform.logging',
                    'platform.tracing',
                ],
                'disabled' => [
                    'platform.http',
                ],
                'featureBundles' => [
                    'observability' => 'minimal',
                    'profile' => [
                        'level' => 'standard',
                        'name' => 'hybrid',
                    ],
                ],
                'metadata' => [
                    'owner' => [
                        'package' => 'core.kernel',
                        'type' => 'runtime',
                    ],
                    'stage' => 'contract',
                ],
            ],
            $payload,
        );
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
