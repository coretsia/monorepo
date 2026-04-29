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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use PHPUnit\Framework\TestCase;

final class ModuleDescriptorIdIsDerivedFromLayerAndSlugTest extends TestCase
{
    public function test_derives_module_id_from_layer_and_slug(): void
    {
        $descriptor = ModuleDescriptor::fromLayerAndSlug(
            layer: 'Platform',
            slug: 'Cli',
            composerName: 'coretsia/does-not-affect-identity',
            packageKind: 'runtime',
            moduleClass: 'Coretsia\\Example\\DoesNotAffectIdentity',
        );

        self::assertSame('platform.cli', $descriptor->moduleId());
        self::assertSame('platform', $descriptor->layer());
        self::assertSame('cli', $descriptor->slug());

        self::assertSame('platform.cli', $descriptor->id()->value());
        self::assertSame('platform.cli', $descriptor->toArray()['moduleId']);
        self::assertSame('platform', $descriptor->toArray()['layer']);
        self::assertSame('cli', $descriptor->toArray()['slug']);
    }

    public function test_composer_metadata_does_not_affect_module_identity(): void
    {
        $left = ModuleDescriptor::fromLayerAndSlug(
            layer: 'core',
            slug: 'kernel',
            composerName: 'coretsia/core-kernel',
            packageKind: 'runtime',
            moduleClass: 'Coretsia\\Core\\Kernel\\KernelModule',
            metadata: [
                'source' => 'left',
            ],
        );

        $right = ModuleDescriptor::fromLayerAndSlug(
            layer: 'core',
            slug: 'kernel',
            composerName: 'vendor/custom-package-name',
            packageKind: 'library',
            moduleClass: 'Vendor\\Custom\\DifferentModule',
            metadata: [
                'source' => 'right',
            ],
        );

        self::assertSame($left->moduleId(), $right->moduleId());
        self::assertSame($left->layer(), $right->layer());
        self::assertSame($left->slug(), $right->slug());
        self::assertTrue($left->id()->equals($right->id()));
    }

    public function test_exports_internal_module_id_as_scalars_not_object_identity(): void
    {
        $descriptor = ModuleDescriptor::fromLayerAndSlug('presets', 'micro');

        self::assertInstanceOf(ModuleId::class, $descriptor->id());

        $exported = $descriptor->toArray();

        self::assertIsString($exported['moduleId']);
        self::assertIsString($exported['layer']);
        self::assertIsString($exported['slug']);

        self::assertSame('presets.micro', $exported['moduleId']);
        self::assertSame('presets', $exported['layer']);
        self::assertSame('micro', $exported['slug']);

        self::assertArrayNotHasKey('id', $exported);
        self::assertNoObjectsInExportedShape($exported);
    }

    public function test_sorts_capabilities_and_metadata_deterministically(): void
    {
        $descriptor = ModuleDescriptor::fromLayerAndSlug(
            layer: 'enterprise',
            slug: 'audit',
            capabilities: [
                'runtime.audit',
                'runtime.http',
                'runtime.audit',
                'runtime.cli',
            ],
            metadata: [
                'zeta' => [
                    'b' => 2,
                    'a' => 1,
                ],
                'alpha' => [
                    'items' => [
                        'second',
                        'first',
                    ],
                ],
            ],
        );

        self::assertSame([
            'runtime.audit',
            'runtime.cli',
            'runtime.http',
        ], $descriptor->capabilities());

        self::assertSame([
            'alpha' => [
                'items' => [
                    'second',
                    'first',
                ],
            ],
            'zeta' => [
                'a' => 1,
                'b' => 2,
            ],
        ], $descriptor->metadata());

        self::assertSame($descriptor->metadata(), $descriptor->toArray()['metadata']);
        self::assertSame($descriptor->capabilities(), $descriptor->toArray()['capabilities']);
    }

    public function test_rejects_tooling_only_layer_as_runtime_descriptor(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModuleDescriptor::fromLayerAndSlug('devtools', 'cli-spikes');
    }

    /**
     * @param array<mixed> $value
     */
    private static function assertNoObjectsInExportedShape(array $value, string $path = 'root'): void
    {
        foreach ($value as $key => $item) {
            $itemPath = $path . '.' . (string)$key;

            self::assertFalse(
                is_object($item),
                'Exported descriptor shape contains object at ' . $itemPath . '.',
            );

            if (is_array($item)) {
                self::assertNoObjectsInExportedShape($item, $itemPath);
            }
        }
    }
}
