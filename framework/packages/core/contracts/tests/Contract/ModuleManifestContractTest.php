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
use Coretsia\Contracts\Module\ModuleManifest;
use PHPUnit\Framework\TestCase;

final class ModuleManifestContractTest extends TestCase
{
    public function testManifestSortsDescriptorsByModuleId(): void
    {
        $manifest = new ModuleManifest([
            ModuleDescriptor::fromLayerAndSlug('platform', 'beta'),
            ModuleDescriptor::fromLayerAndSlug('platform', 'alpha'),
            ModuleDescriptor::fromLayerAndSlug('core', 'kernel'),
        ]);

        self::assertSame(
            ['core.kernel', 'platform.alpha', 'platform.beta'],
            $manifest->ids(),
        );

        self::assertSame(
            ['core.kernel', 'platform.alpha', 'platform.beta'],
            array_map(
                static fn (ModuleDescriptor $descriptor): string => $descriptor->moduleId(),
                $manifest->modules(),
            ),
        );
    }

    public function testManifestRejectsDuplicateModuleIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Module manifest contains duplicate module id.');

        new ModuleManifest([
            ModuleDescriptor::fromLayerAndSlug('platform', 'http'),
            ModuleDescriptor::fromLayerAndSlug('platform', 'http'),
        ]);
    }

    public function testManifestLookupIsStableAndNormalizesModuleIdInput(): void
    {
        $descriptor = ModuleDescriptor::fromLayerAndSlug('platform', 'http');

        $manifest = new ModuleManifest([$descriptor]);

        self::assertTrue($manifest->has('platform.http'));
        self::assertTrue($manifest->has(' Platform.Http '));
        self::assertFalse($manifest->has('invalid'));

        self::assertSame($descriptor, $manifest->get('platform.http'));
        self::assertSame($descriptor, $manifest->get(' Platform.Http '));
        self::assertNull($manifest->get('invalid'));
    }

    public function testManifestExportedShapeIsDeterministicAndJsonLike(): void
    {
        $manifest = new ModuleManifest([
            ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                composerName: 'coretsia/http',
                packageKind: 'runtime',
                moduleClass: 'Coretsia\\Platform\\Http\\HttpModule',
                capabilities: ['http.server'],
                metadata: ['owner' => 'platform'],
            ),
        ]);

        self::assertSame(1, $manifest->schemaVersion());

        self::assertSame(
            ['moduleIds', 'modules', 'schemaVersion'],
            array_keys($manifest->toArray()),
        );

        self::assertSame(
            [
                'moduleIds' => ['platform.http'],
                'modules' => [
                    [
                        'capabilities' => ['http.server'],
                        'composerName' => 'coretsia/http',
                        'layer' => 'platform',
                        'metadata' => ['owner' => 'platform'],
                        'moduleClass' => 'Coretsia\\Platform\\Http\\HttpModule',
                        'moduleId' => 'platform.http',
                        'packageKind' => 'runtime',
                        'schemaVersion' => 1,
                        'slug' => 'http',
                    ],
                ],
                'schemaVersion' => 1,
            ],
            $manifest->toArray(),
        );
    }
}
