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
    public function testExportsExactPresetPolicyShapeAndSortedCollections(): void
    {
        $preset = new ModePreset(
            schemaVersion: 1,
            name: 'hybrid',
            description: 'Hybrid web application mode.',
            required: [ModuleId::fromString('platform.cli'), ModuleId::fromString('core.kernel')],
            modules: [ModuleId::fromString('platform.tracing'), ModuleId::fromString('platform.logging')],
            featureBundles: ['profile' => ['level' => 'standard']],
            metadata: ['owner' => ['package' => 'core.kernel']],
        );
        self::assertSame(
            ['core.kernel', 'platform.cli'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $preset->required()),
        );
        self::assertSame(
            ['platform.logging', 'platform.tracing'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $preset->modules()),
        );
        self::assertSame(
            ['schemaVersion', 'name', 'description', 'required', 'modules', 'featureBundles', 'metadata'],
            \array_keys($preset->toArray()),
        );
        self::assertSame(['core.kernel', 'platform.cli'], $preset->toArray()['required']);
        self::assertSame(['platform.logging', 'platform.tracing'], $preset->toArray()['modules']);
        self::assertSame(['profile' => ['level' => 'standard']], $preset->toArray()['featureBundles']);
        self::assertSame(['owner' => ['package' => 'core.kernel']], $preset->toArray()['metadata']);
    }
}
