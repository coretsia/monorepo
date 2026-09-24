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
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanArtifactHydrator;
use Coretsia\Kernel\Module\ModulePlanEntry;
use PHPUnit\Framework\TestCase;

final class ModulePlanArtifactHydratorDoesNotExposeLegacySelectionStateTest extends TestCase
{
    public function testHydrationUsesOnlyValidatedArtifactPayloadWithoutPhaseA(): void
    {
        $id = ModuleId::fromString('core.kernel');
        $payload = new ModulePlan(
            'web',
            [$id],
            [],
            [$id],
            [new ModulePlanEntry(moduleId: $id, composerName: 'coretsia/core-kernel')],
        )->toArray();
        $hydrated = new ModulePlanArtifactHydrator()->hydrate($payload);
        self::assertSame($payload, $hydrated->toArray());
        foreach (['preset', 'optionalMissing', 'disabled', 'warnings'] as $legacy) {
            self::assertArrayNotHasKey($legacy, $hydrated->toArray());
        }
        $source = \file_get_contents(new \ReflectionClass(ModulePlanArtifactHydrator::class)->getFileName());
        self::assertIsString($source);
        foreach (['ModePresetLoaderFactory', 'ManifestReaderInterface', 'ModuleSelectionFactory'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }
}
