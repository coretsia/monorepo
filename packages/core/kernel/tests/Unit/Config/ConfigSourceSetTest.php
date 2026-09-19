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

namespace Coretsia\Kernel\Tests\Unit\Config;

use Coretsia\Kernel\Config\Source\ConfigSourceSet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigSourceSetTest extends TestCase
{
    public function testEmptyReturnsSixEmptyLists(): void
    {
        $set = ConfigSourceSet::empty();

        self::assertSame([], $set->packageDefaultSources());
        self::assertSame([], $set->packageRuleSources());
        self::assertSame([], $set->splitRoots());
        self::assertSame([], $set->explicitRuleSources());
        self::assertSame([], $set->explicitEnvOverlayMappings());
        self::assertSame([], $set->modePresetSourceCandidates());
    }

    public function testAccessorsPreserveSuppliedCanonicalLists(): void
    {
        $packageDefaultSources = [
            [
                'root' => 'kernel',
                'packageId' => 'core/kernel',
                'moduleId' => 'core.kernel',
                'path' => 'config/kernel.php',
                'filesystemPath' => '/tmp/kernel/config/kernel.php',
                'sourceId' => 'core/kernel/config/defaults/kernel',
                'precedence' => 10,
            ],
        ];
        $packageRuleSources = [
            [
                'root' => 'kernel',
                'packageId' => 'core/kernel',
                'moduleId' => 'core.kernel',
                'path' => 'config/rules.php',
                'filesystemPath' => '/tmp/kernel/config/rules.php',
                'sourceId' => 'core/kernel/config/rules/kernel',
                'precedence' => 20,
            ],
        ];
        $splitRoots = ['kernel'];
        $explicitRuleSources = [
            [
                'root' => 'custom',
                'packageId' => 'app/custom',
                'moduleId' => null,
                'path' => 'custom/config/rules.php',
                'filesystemPath' => '/tmp/app/custom/config/rules.php',
                'sourceId' => 'app/custom/rules',
                'precedence' => 30,
            ],
        ];
        $explicitEnvOverlayMappings = [
            [
                'path' => 'kernel.debug',
                'env' => 'KERNEL_DEBUG',
                'type' => 'bool',
                'sourceId' => 'env/KERNEL_DEBUG',
                'precedence' => 500,
                'allowedValues' => [null, true, false, '1', '0'],
            ],
        ];
        $modePresetSourceCandidates = [
            [
                'path' => 'resources/modes/micro.php',
                'filesystemPath' => '/tmp/kernel/resources/modes/micro.php',
                'sourceId' => 'core/kernel:resources/modes/micro.php',
                'precedence' => 10,
            ],
        ];

        $set = new ConfigSourceSet(
            packageDefaultSources: $packageDefaultSources,
            packageRuleSources: $packageRuleSources,
            splitRoots: $splitRoots,
            explicitRuleSources: $explicitRuleSources,
            explicitEnvOverlayMappings: $explicitEnvOverlayMappings,
            modePresetSourceCandidates: $modePresetSourceCandidates,
        );

        self::assertSame($packageDefaultSources, $set->packageDefaultSources());
        self::assertSame($packageRuleSources, $set->packageRuleSources());
        self::assertSame($splitRoots, $set->splitRoots());
        self::assertSame($explicitRuleSources, $set->explicitRuleSources());
        self::assertSame($explicitEnvOverlayMappings, $set->explicitEnvOverlayMappings());
        self::assertSame($modePresetSourceCandidates, $set->modePresetSourceCandidates());
    }

    #[DataProvider('keyedTopLevelCollections')]
    public function testRejectsKeyedTopLevelArrays(
        string $field,
        string $expectedMessage,
    ): void {
        $arguments = [
            'packageDefaultSources' => [],
            'packageRuleSources' => [],
            'splitRoots' => [],
            'explicitRuleSources' => [],
            'explicitEnvOverlayMappings' => [],
            'modePresetSourceCandidates' => [],
        ];
        $arguments[$field] = ['keyed' => []];

        try {
            new ConfigSourceSet(
                packageDefaultSources: $arguments['packageDefaultSources'],
                packageRuleSources: $arguments['packageRuleSources'],
                splitRoots: $arguments['splitRoots'],
                explicitRuleSources: $arguments['explicitRuleSources'],
                explicitEnvOverlayMappings: $arguments['explicitEnvOverlayMappings'],
                modePresetSourceCandidates: $arguments['modePresetSourceCandidates'],
            );

            self::fail('Expected keyed top-level collection to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
        }
    }

    public function testDoesNotExposeBroadSerializationApi(): void
    {
        $set = ConfigSourceSet::empty();

        self::assertFalse(\method_exists($set, 'toArray'));
        self::assertFalse(\method_exists($set, 'jsonSerialize'));
        self::assertNotInstanceOf(\JsonSerializable::class, $set);
    }

    /**
     * @return iterable<string, array{field: string, expectedMessage: string}>
     */
    public static function keyedTopLevelCollections(): iterable
    {
        yield 'package defaults' => [
            'field' => 'packageDefaultSources',
            'expectedMessage' => 'config-source-set-package-default-sources-must-be-list',
        ];
        yield 'package rules' => [
            'field' => 'packageRuleSources',
            'expectedMessage' => 'config-source-set-package-rule-sources-must-be-list',
        ];
        yield 'split roots' => [
            'field' => 'splitRoots',
            'expectedMessage' => 'config-source-set-split-roots-must-be-list',
        ];
        yield 'explicit rules' => [
            'field' => 'explicitRuleSources',
            'expectedMessage' => 'config-source-set-explicit-rule-sources-must-be-list',
        ];
        yield 'explicit env overlay mappings' => [
            'field' => 'explicitEnvOverlayMappings',
            'expectedMessage' => 'config-source-set-explicit-env-overlay-mappings-must-be-list',
        ];
        yield 'mode preset candidates' => [
            'field' => 'modePresetSourceCandidates',
            'expectedMessage' => 'config-source-set-mode-preset-source-candidates-must-be-list',
        ];
    }
}
