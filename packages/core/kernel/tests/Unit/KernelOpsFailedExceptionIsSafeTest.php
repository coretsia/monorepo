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

use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class KernelOpsFailedExceptionIsSafeTest extends TestCase
{
    public function testPublicFailureSurfaceIsStableAndDoesNotRetainPreviousThrowable(): void
    {
        $rawValue = 'raw-kernel-ops-secret-value';
        $absolutePath = self::absolutePathFixture();
        $previous = new \RuntimeException('previous failure: ' . $rawValue . ' at ' . $absolutePath);

        foreach (
            [
                KernelOpsFailedException::REASON_OPERATION_FAILED,
                KernelOpsFailedException::REASON_HOST_BOOT_FAILED,
            ] as $reason
        ) {
            $exception = new KernelOpsFailedException($reason);

            self::assertSame('CORETSIA_KERNEL_OPS_FAILED', $exception->errorCode());
            self::assertSame($reason, $exception->reason());
            self::assertSame(
                'CORETSIA_KERNEL_OPS_FAILED: ' . $reason,
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());

            $surface = \var_export(
                [
                    'message' => $exception->getMessage(),
                    'errorCode' => $exception->errorCode(),
                    'reason' => $exception->reason(),
                    'previous' => $exception->getPrevious(),
                ],
                true,
            );

            self::assertStringNotContainsString($previous->getMessage(), $surface);
            self::assertStringNotContainsString($rawValue, $surface);
            self::assertStringNotContainsString($absolutePath, $surface);
        }
    }

    public function testReasonsAreExactlyOperationFailedAndHostBootFailed(): void
    {
        $reflection = new ReflectionClass(KernelOpsFailedException::class);
        $reasons = $reflection->getConstant('REASONS');

        self::assertSame(
            [
                KernelOpsFailedException::REASON_HOST_BOOT_FAILED => true,
                KernelOpsFailedException::REASON_OPERATION_FAILED => true,
            ],
            $reasons,
        );

        self::assertSame(
            'operation-failed',
            KernelOpsFailedException::REASON_OPERATION_FAILED,
        );
        self::assertSame(
            'host-boot-failed',
            KernelOpsFailedException::REASON_HOST_BOOT_FAILED,
        );

        try {
            new KernelOpsFailedException('raw-unapproved-reason');

            self::fail('Expected an unapproved Kernel Ops failure reason to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                'kernel-ops-failed-reason-invalid',
                $exception->getMessage(),
            );
        }
    }

    private static function absolutePathFixture(): string
    {
        return \DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\private\\kernel-ops\\secret.txt'
            : '/private/kernel-ops/secret.txt';
    }
}
