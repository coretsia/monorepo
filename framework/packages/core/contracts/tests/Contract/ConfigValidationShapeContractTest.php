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

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValidationViolation;
use PHPUnit\Framework\TestCase;

final class ConfigValidationShapeContractTest extends TestCase
{
    public function test_config_validation_violation_exports_schema_version(): void
    {
        $violation = new ConfigValidationViolation(
            root: 'foundation',
            path: 'container.bindings',
            reason: 'CONFIG_RULE_INVALID',
            expected: 'map',
            actualType: 'string',
        );

        self::assertSame(1, $violation->schemaVersion());

        self::assertSame(
            [
                'actualType' => 'string',
                'expected' => 'map',
                'path' => 'container.bindings',
                'reason' => 'CONFIG_RULE_INVALID',
                'root' => 'foundation',
                'schemaVersion' => 1,
            ],
            $violation->toArray(),
        );

        self::assertSame(
            [
                'actualType',
                'expected',
                'path',
                'reason',
                'root',
                'schemaVersion',
            ],
            array_keys($violation->toArray()),
        );
    }

    public function test_config_validation_result_exports_schema_version(): void
    {
        $violation = new ConfigValidationViolation(
            root: 'foundation',
            path: 'container.bindings',
            reason: 'CONFIG_RULE_INVALID',
        );

        $result = ConfigValidationResult::failure([$violation]);

        self::assertSame(1, $result->schemaVersion());

        self::assertSame(
            [
                'schemaVersion' => 1,
                'success' => false,
                'violations' => [
                    [
                        'path' => 'container.bindings',
                        'reason' => 'CONFIG_RULE_INVALID',
                        'root' => 'foundation',
                        'schemaVersion' => 1,
                    ],
                ],
            ],
            $result->toArray(),
        );

        self::assertSame(
            [
                'schemaVersion',
                'success',
                'violations',
            ],
            array_keys($result->toArray()),
        );
    }

    public function test_success_result_exports_empty_versioned_shape(): void
    {
        $result = ConfigValidationResult::success();

        self::assertSame(1, $result->schemaVersion());

        self::assertSame(
            [
                'schemaVersion' => 1,
                'success' => true,
                'violations' => [],
            ],
            $result->toArray(),
        );

        self::assertSame(
            [
                'schemaVersion',
                'success',
                'violations',
            ],
            array_keys($result->toArray()),
        );
    }
}
