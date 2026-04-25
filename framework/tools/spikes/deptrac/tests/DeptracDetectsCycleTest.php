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

namespace Coretsia\Tools\Spikes\deptrac\tests;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\deptrac\DeptracGenerate;
use PHPUnit\Framework\TestCase;

final class DeptracDetectsCycleTest extends TestCase
{
    public function testGeneratorThrowsOnCycleFixture(): void
    {
        try {
            DeptracGenerate::generateYamlFromFixture('deptrac_min/package_index_cycle.php');
            self::fail('Expected DeterministicException for cycle');
        } catch (DeterministicException $e) {
            $this->assertDeterministicErrorCodeLike($e, ErrorCodes::CORETSIA_DEPTRAC_CYCLE_DETECTED);
        }
    }

    private function assertDeterministicErrorCodeLike(\Throwable $e, string $expectedCode): void
    {
        $msg = $e->getMessage();

        if (method_exists($e, 'code')) {
            /** @var mixed $code */
            $code = $e->code();
            self::assertSame($expectedCode, $code);
            return;
        }

        if (method_exists($e, 'getErrorCode')) {
            /** @var mixed $code */
            $code = $e->getErrorCode();
            self::assertSame($expectedCode, $code);
            return;
        }

        self::assertStringContainsString($expectedCode, $msg, 'Deterministic error code must be visible');
    }
}
