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

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use PHPUnit\Framework\TestCase;

final class ErrorDescriptorHttpStatusIsOptionalContractTest extends TestCase
{
    public function test_http_status_defaults_to_null(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
        );

        self::assertNull($descriptor->httpStatus());
        self::assertNull($descriptor->toArray()['httpStatus']);
    }

    public function test_http_status_accepts_valid_hint_range(): void
    {
        foreach ([100, 200, 404, 500, 599] as $status) {
            $descriptor = new ErrorDescriptor(
                code: 'core.example',
                message: 'Example message.',
                severity: ErrorSeverity::Error,
                httpStatus: $status,
            );

            self::assertSame($status, $descriptor->httpStatus());
            self::assertSame($status, $descriptor->toArray()['httpStatus']);
        }
    }

    public function test_http_status_rejects_values_outside_http_status_range(): void
    {
        foreach ([0, 99, 600, 999] as $status) {
            try {
                new ErrorDescriptor(
                    code: 'core.example',
                    message: 'Example message.',
                    httpStatus: $status,
                );

                self::fail(sprintf('Expected httpStatus "%d" to be rejected.', $status));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_http_status_is_only_a_hint_field_in_public_shape(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            severity: ErrorSeverity::Warning,
            httpStatus: 400,
        );

        self::assertSame(
            [
                'code',
                'extensions',
                'httpStatus',
                'message',
                'schemaVersion',
                'severity',
            ],
            array_keys($descriptor->toArray()),
        );

        self::assertArrayNotHasKey('request', $descriptor->toArray());
        self::assertArrayNotHasKey('response', $descriptor->toArray());
        self::assertArrayNotHasKey('headers', $descriptor->toArray());
        self::assertArrayNotHasKey('problemDetails', $descriptor->toArray());
    }
}
