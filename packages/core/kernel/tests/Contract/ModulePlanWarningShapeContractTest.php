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
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\Warning\ModuleOptionalMissingWarning;
use PHPUnit\Framework\TestCase;

final class ModulePlanWarningShapeContractTest extends TestCase
{
    public function testOptionalMissingWarningExportsStableShape(): void
    {
        $warning = ModuleOptionalMissingWarning::forPresetOptionalModule(
            moduleId: ModuleId::fromString('platform.metrics'),
            preset: 'micro',
        );

        self::assertSame(ModuleErrorCodes::CORETSIA_MODULE_OPTIONAL_MISSING, $warning->code());
        self::assertSame('platform.metrics', $warning->moduleId());
        self::assertSame('micro', $warning->preset());
        self::assertSame(
            ModuleOptionalMissingWarning::REASON_PRESET_OPTIONAL_MODULE_MISSING,
            $warning->reason(),
        );

        self::assertSame(
            ModuleErrorCodes::CORETSIA_MODULE_OPTIONAL_MISSING
            . "\0" . 'micro'
            . "\0" . 'platform.metrics'
            . "\0" . ModuleOptionalMissingWarning::REASON_PRESET_OPTIONAL_MODULE_MISSING,
            $warning->canonicalKey(),
        );

        $payload = $warning->toArray();

        self::assertSame(
            [
                'code',
                'moduleId',
                'preset',
                'reason',
            ],
            \array_keys($payload),
        );

        self::assertSame(
            [
                'code' => ModuleErrorCodes::CORETSIA_MODULE_OPTIONAL_MISSING,
                'moduleId' => 'platform.metrics',
                'preset' => 'micro',
                'reason' => ModuleOptionalMissingWarning::REASON_PRESET_OPTIONAL_MODULE_MISSING,
            ],
            $payload,
        );
    }

    public function testOptionalMissingWarningRejectsUnsafePresetNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('module-optional-missing-warning-preset-invalid');

        ModuleOptionalMissingWarning::forPresetOptionalModule(
            moduleId: ModuleId::fromString('platform.metrics'),
            preset: '../micro',
        );
    }
}
