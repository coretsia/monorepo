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
use Coretsia\Kernel\Module\ResolvedModuleOverrides;
use PHPUnit\Framework\TestCase;

final class ResolvedModuleOverridesContractTest extends TestCase
{
    public function testCanonicalOverridesAreImmutable(): void
    {
        $overrides = new ResolvedModuleOverrides(
            include: [ModuleId::fromString('core.foundation')],
            exclude: [ModuleId::fromString('platform.http')],
        );
        self::assertSame('core.foundation', $overrides->include()[0]->value());
        self::assertSame('platform.http', $overrides->exclude()[0]->value());
        self::assertTrue(new \ReflectionClass($overrides)->isReadOnly());
    }

    public function testRejectsDuplicateIncludeBeforeNormalization(): void
    {
        $this->expectException(InvalidModuleSelectionException::class);
        new ResolvedModuleOverrides(
            include: [ModuleId::fromString('core.kernel'), ModuleId::fromString('core.kernel')],
            exclude: [],
        );
    }

    public function testRejectsOverlappingOverrides(): void
    {
        $this->expectException(InvalidModuleSelectionException::class);
        $this->expectExceptionMessage(InvalidModuleSelectionException::REASON_OVERLAP);
        new ResolvedModuleOverrides(include: [ModuleId::fromString('core.kernel')], exclude: [
            ModuleId::fromString(
                'core.kernel',
            ),
        ]);
    }

    public function testRejectsAssociativeExcludeAndUnsortedInclude(): void
    {
        foreach (
            [
                [
                    ['core' => ModuleId::fromString('core.kernel')],
                    [],
                    InvalidModuleSelectionException::REASON_INCLUDE_INVALID,
                ],
                [
                    [ModuleId::fromString('platform.worker'), ModuleId::fromString('core.kernel')],
                    [],
                    InvalidModuleSelectionException::REASON_INCLUDE_INVALID,
                ],
                [
                    [],
                    ['http' => ModuleId::fromString('platform.http')],
                    InvalidModuleSelectionException::REASON_EXCLUDED_INVALID,
                ],
            ] as [$include, $exclude, $expectedReason]
        ) {
            try {
                new ResolvedModuleOverrides(include: $include, exclude: $exclude);

                self::fail('Expected non-canonical overrides to fail.');
            } catch (InvalidModuleSelectionException $exception) {
                self::assertSame($expectedReason, $exception->reason());
            }
        }
    }
}
