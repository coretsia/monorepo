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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModePresetConstructorPolicyContractTest extends TestCase
{
    /**
     * @param array<string, mixed> $featureBundles
     * @param array<string, mixed> $metadata
     */
    #[DataProvider('unsafeDirectConstructionProvider')]
    public function testModePresetDirectConstructionRejectsSchemaWeakerValues(
        string $name,
        ?string $description,
        array $featureBundles,
        array $metadata,
        string $expectedReason,
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedReason);

        new ModePreset(
            schemaVersion: 1,
            name: $name,
            description: $description,
            required: [
                self::moduleId('core.kernel'),
            ],
            optional: [],
            disabled: [],
            featureBundles: $featureBundles,
            metadata: $metadata,
        );
    }

    /**
     * @return iterable<string, array{
     *     name: string,
     *     description: string|null,
     *     featureBundles: array<string, mixed>,
     *     metadata: array<string, mixed>,
     *     expectedReason: string
     * }>
     */
    public static function unsafeDirectConstructionProvider(): iterable
    {
        yield 'preset-name-too-long' => [
            'name' => \str_repeat('a', 65),
            'description' => 'Safe preset description.',
            'featureBundles' => [],
            'metadata' => [],
            'expectedReason' => 'mode-preset-name-invalid',
        ];

        yield 'description-path-like' => [
            'name' => 'micro',
            'description' => '../secret',
            'featureBundles' => [],
            'metadata' => [],
            'expectedReason' => 'mode-preset-description-invalid',
        ];

        yield 'feature-bundles-path-like-key' => [
            'name' => 'micro',
            'description' => 'Safe preset description.',
            'featureBundles' => [
                '../secret' => true,
            ],
            'metadata' => [],
            'expectedReason' => 'mode-preset-featureBundles-map-key-invalid',
        ];

        yield 'metadata-path-like-string' => [
            'name' => 'micro',
            'description' => 'Safe preset description.',
            'featureBundles' => [],
            'metadata' => [
                'safe' => '../secret',
            ],
            'expectedReason' => 'mode-preset-metadata-string-invalid',
        ];

        yield 'metadata-string-too-long' => [
            'name' => 'micro',
            'description' => 'Safe preset description.',
            'featureBundles' => [],
            'metadata' => [
                'safe' => \str_repeat('a', 1025),
            ],
            'expectedReason' => 'mode-preset-metadata-string-invalid',
        ];
    }

    public function testModePresetDirectConstructionAcceptsSchemaEquivalentSafeValues(): void
    {
        $preset = new ModePreset(
            schemaVersion: 1,
            name: \str_repeat('a', 64),
            description: 'Safe preset description.',
            required: [
                self::moduleId('core.kernel'),
            ],
            optional: [
                self::moduleId('platform.http'),
            ],
            disabled: [],
            featureBundles: [
                'profile' => [
                    'level' => 'standard',
                ],
            ],
            metadata: [
                'owner' => [
                    'package' => 'core.kernel',
                ],
            ],
        );

        self::assertSame(\str_repeat('a', 64), $preset->name());
        self::assertSame('Safe preset description.', $preset->description());
    }

    private static function moduleId(string $value): ModuleId
    {
        return ModuleId::fromString($value);
    }
}
