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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\DependencySync\Catalog\ReleaseInstallationCatalog;
use Coretsia\Kernel\Module\ModulePlanEntry;
use PHPUnit\Framework\TestCase;

final class ReleaseInstallationCatalogTest extends TestCase
{
    public function testIndexesCanonicalEntries(): void
    {
        $foundation = new ModulePlanEntry(
            ModuleId::fromString('core.foundation'),
            'coretsia/core-foundation',
        );
        $kernel = new ModulePlanEntry(
            ModuleId::fromString('core.kernel'),
            'coretsia/core-kernel',
            [$foundation->moduleId()],
        );

        $catalog = new ReleaseInstallationCatalog(
            '0.7',
            '^0.7.0',
            [$foundation, $kernel],
        );

        self::assertSame('0.7', $catalog->releaseLine());
        self::assertSame('^0.7.0', $catalog->publicConstraint());
        self::assertSame(
            $kernel,
            $catalog->entry(ModuleId::fromString('core.kernel')),
        );
    }

    public function testRejectsInconsistentReleaseLineOrderingAndDanglingEdges(): void
    {
        $foundation = new ModulePlanEntry(
            ModuleId::fromString('core.foundation'),
            'coretsia/core-foundation',
        );
        $kernel = new ModulePlanEntry(
            ModuleId::fromString('core.kernel'),
            'coretsia/core-kernel',
            [$foundation->moduleId()],
        );
        $kernelWithDanglingDependency = new ModulePlanEntry(
            ModuleId::fromString('core.kernel'),
            'coretsia/core-kernel',
            [ModuleId::fromString('platform.worker')],
        );

        $cases = [
            ['0.7', '^0.8.0', [$foundation]],
            ['0.7', '^0.7.0', [$kernel, $foundation]],
            ['0.7', '^0.7.0', [$kernelWithDanglingDependency]],
        ];

        foreach ($cases as $arguments) {
            try {
                new ReleaseInstallationCatalog(...$arguments);
                self::fail('Expected invalid release installation catalog.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
