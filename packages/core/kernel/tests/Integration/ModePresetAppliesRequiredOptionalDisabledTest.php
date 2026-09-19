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

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use PHPUnit\Framework\TestCase;

final class ModePresetAppliesRequiredOptionalDisabledTest extends TestCase
{
    public function testRequiredOptionalAndDisabledSetsAreCanonicalizedDeterministically(): void
    {
        $preset = new ModePresetSchemaValidator()->validate(
            'hybrid',
            [
                'schemaVersion' => 1,
                'name' => 'hybrid',
                'description' => 'Hybrid test mode.',
                'required' => [
                    'platform.cli',
                    'core.kernel',
                    'core.foundation',
                    'core.kernel',
                ],
                'optional' => [
                    'platform.metrics',
                    'platform.http',
                    'platform.logging',
                    'platform.http',
                ],
                'disabled' => [
                    'platform.tracing',
                    'platform.tracing',
                ],
                'featureBundles' => [
                    'zeta' => [
                        'enabled' => true,
                    ],
                    'alpha' => [
                        'enabled' => true,
                    ],
                ],
                'metadata' => [
                    'zeta' => 2,
                    'alpha' => 1,
                ],
            ],
        );

        self::assertSame('hybrid', $preset->name());

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            self::moduleIdValues($preset->required()),
        );

        self::assertSame(
            [
                'platform.http',
                'platform.logging',
                'platform.metrics',
            ],
            self::moduleIdValues($preset->optional()),
        );

        self::assertSame(
            [
                'platform.tracing',
            ],
            self::moduleIdValues($preset->disabled()),
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
                'platform.http',
                'platform.logging',
                'platform.metrics',
            ],
            self::moduleIdValues($preset->moduleIds()),
        );

        self::assertNotContains('platform.tracing', self::moduleIdValues($preset->moduleIds()));

        self::assertSame(
            [
                'alpha',
                'zeta',
            ],
            \array_keys($preset->featureBundles()),
        );

        self::assertSame(
            [
                'alpha',
                'zeta',
            ],
            \array_keys($preset->metadata()),
        );
    }

    public function testExportedShapeContainsCanonicalRequiredOptionalDisabledLists(): void
    {
        $preset = new ModePresetSchemaValidator()->validate(
            'micro',
            [
                'schemaVersion' => 1,
                'name' => 'micro',
                'description' => 'Micro test mode.',
                'required' => [
                    'core.kernel',
                    'platform.cli',
                    'core.foundation',
                ],
                'optional' => [
                    'platform.metrics',
                    'platform.logging',
                ],
                'disabled' => [
                    'platform.tracing',
                ],
                'featureBundles' => [],
                'metadata' => [],
            ],
        );

        self::assertSame(
            [
                'schemaVersion' => 1,
                'name' => 'micro',
                'description' => 'Micro test mode.',
                'required' => [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                ],
                'optional' => [
                    'platform.logging',
                    'platform.metrics',
                ],
                'disabled' => [
                    'platform.tracing',
                ],
                'featureBundles' => [],
                'metadata' => [],
            ],
            $preset->toArray(),
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
