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
use Coretsia\Kernel\Module\Exception\InvalidModuleSelectionException;
use Coretsia\Kernel\Module\ModuleSelection;
use PHPUnit\Framework\TestCase;

final class ModuleSelectionContractTest extends TestCase
{
    public function testAcceptsImmutableCanonicalLists(): void
    {
        $selection = new ModuleSelection(
            roots: [ModuleId::fromString('core.foundation'), ModuleId::fromString('core.kernel')],
            excluded: [ModuleId::fromString('platform.http')],
        );
        self::assertSame(
            ['core.foundation', 'core.kernel'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $selection->roots()),
        );
        self::assertSame(
            ['platform.http'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $selection->excluded()),
        );
        self::assertTrue(new \ReflectionClass($selection)->isReadOnly());
    }

    public function testRejectsUnsortedDirectRoots(): void
    {
        $this->expectException(InvalidModuleSelectionException::class);
        $this->expectExceptionMessage(InvalidModuleSelectionException::REASON_ROOTS_INVALID);
        new ModuleSelection(
            roots: [ModuleId::fromString('core.kernel'), ModuleId::fromString('core.foundation')],
            excluded: [],
        );
    }

    public function testRejectsDuplicateRoots(): void
    {
        $this->expectException(InvalidModuleSelectionException::class);
        new ModuleSelection(
            roots: [ModuleId::fromString('core.kernel'), ModuleId::fromString('core.kernel')],
            excluded: [],
        );
    }

    public function testRejectsAssociativeExcluded(): void
    {
        $this->expectException(InvalidModuleSelectionException::class);
        new ModuleSelection(roots: [], excluded: ['http' => ModuleId::fromString('platform.http')]);
    }

    public function testRejectsOverlappingSetsWithoutRepair(): void
    {
        $this->expectException(InvalidModuleSelectionException::class);
        $this->expectExceptionMessage(InvalidModuleSelectionException::REASON_OVERLAP);
        new ModuleSelection(roots: [ModuleId::fromString('core.kernel')], excluded: [
            ModuleId::fromString('core.kernel'),
        ]);
    }
}
