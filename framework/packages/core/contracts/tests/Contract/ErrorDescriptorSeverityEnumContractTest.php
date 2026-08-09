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

use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use PHPUnit\Framework\TestCase;

final class ErrorDescriptorSeverityEnumContractTest extends TestCase
{
    public function testErrorSeverityCasesAreStable(): void
    {
        self::assertSame(
            [
                'info',
                'warning',
                'error',
                'critical',
            ],
            array_map(
                static fn (ErrorSeverity $severity): string => $severity->value,
                ErrorSeverity::cases(),
            ),
        );
    }

    public function testErrorSeverityValuesHelperIsStable(): void
    {
        self::assertSame(
            [
                'info',
                'warning',
                'error',
                'critical',
            ],
            ErrorSeverity::values(),
        );
    }

    public function testErrorSeverityIsNotALoggerLevelEnum(): void
    {
        self::assertNotContains('debug', ErrorSeverity::values());
        self::assertNotContains('notice', ErrorSeverity::values());
        self::assertNotContains('alert', ErrorSeverity::values());
        self::assertNotContains('emergency', ErrorSeverity::values());
    }
}
