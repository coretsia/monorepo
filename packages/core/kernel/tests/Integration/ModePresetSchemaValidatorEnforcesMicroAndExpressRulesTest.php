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

final class ModePresetSchemaValidatorEnforcesMicroAndExpressRulesTest extends TestCase
{
    public function testFrameworkMicroPresetMatchesCanonicalRules(): void
    {
        $preset = new ModePresetSchemaValidator()->validate(
            'micro',
            self::loadFrameworkPresetPayload('micro'),
        );

        self::assertSame(1, $preset->schemaVersion());
        self::assertSame('micro', $preset->name());
        self::assertSame('Micro web application mode.', $preset->description());

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
                'platform.logging',
                'platform.metrics',
                'platform.tracing',
            ],
            self::moduleIdValues($preset->optional()),
        );

        self::assertSame([], self::moduleIdValues($preset->disabled()));

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
                'platform.logging',
                'platform.metrics',
                'platform.tracing',
            ],
            self::moduleIdValues($preset->moduleIds()),
        );

        self::assertSame(
            [
                'observability' => 'minimal',
            ],
            $preset->featureBundles(),
        );

        self::assertSame([], $preset->metadata());

        self::assertSame(
            [
                'schemaVersion' => 1,
                'name' => 'micro',
                'description' => 'Micro web application mode.',
                'required' => [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                ],
                'optional' => [
                    'platform.logging',
                    'platform.metrics',
                    'platform.tracing',
                ],
                'disabled' => [],
                'featureBundles' => [
                    'observability' => 'minimal',
                ],
                'metadata' => [],
            ],
            $preset->toArray(),
        );
    }

    public function testFrameworkExpressPresetMatchesCanonicalRules(): void
    {
        $preset = new ModePresetSchemaValidator()->validate(
            'express',
            self::loadFrameworkPresetPayload('express'),
        );

        self::assertSame(1, $preset->schemaVersion());
        self::assertSame('express', $preset->name());
        self::assertSame('Express web application mode.', $preset->description());

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
                'platform.http',
            ],
            self::moduleIdValues($preset->required()),
        );

        self::assertSame(
            [
                'platform.logging',
                'platform.metrics',
                'platform.tracing',
            ],
            self::moduleIdValues($preset->optional()),
        );

        self::assertSame([], self::moduleIdValues($preset->disabled()));

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
                'platform.http',
                'platform.logging',
                'platform.metrics',
                'platform.tracing',
            ],
            self::moduleIdValues($preset->moduleIds()),
        );

        self::assertSame(
            [
                'observability' => 'minimal',
            ],
            $preset->featureBundles(),
        );

        self::assertSame([], $preset->metadata());

        self::assertSame(
            [
                'schemaVersion' => 1,
                'name' => 'express',
                'description' => 'Express web application mode.',
                'required' => [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                    'platform.http',
                ],
                'optional' => [
                    'platform.logging',
                    'platform.metrics',
                    'platform.tracing',
                ],
                'disabled' => [],
                'featureBundles' => [
                    'observability' => 'minimal',
                ],
                'metadata' => [],
            ],
            $preset->toArray(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadFrameworkPresetPayload(string $presetName): array
    {
        $file = \dirname(__DIR__, 2) . '/resources/modes/' . $presetName . '.php';

        $payload = (static function (string $presetFile): mixed {
            return require $presetFile;
        })(
            $file
        );

        self::assertIsArray($payload);
        self::assertFalse(\array_is_list($payload));

        return $payload;
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
