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

use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use PHPUnit\Framework\TestCase;

final class DependencySyncExceptionTest extends TestCase
{
    public function testReasonContextAndPreviousAreStableAndSafe(): void
    {
        $exception = DependencySyncException::forCode(
            DependencySyncErrorCodes::RECOVERY_REQUIRED,
            [
                'sourceReason' => 'preset-invalid',
                'sourceErrorCode' => 'MODE_PRESET_INVALID',
                'causeCode' => DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                'recoveryReceiptId' => 'receipt_1',
            ],
        );

        self::assertSame('recovery-required', $exception->reason());
        self::assertSame(
            [
                'causeCode' => DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                'recoveryReceiptId' => 'receipt_1',
                'sourceErrorCode' => 'MODE_PRESET_INVALID',
                'sourceReason' => 'preset-invalid',
            ],
            $exception->context(),
        );
        self::assertNull($exception->getPrevious());
        self::assertSame(
            'RECOVERY_REQUIRED: recovery-required',
            $exception->getMessage(),
        );
        self::assertStringNotContainsString(
            'preset-invalid',
            $exception->getMessage(),
        );
    }

    public function testReasonIsDerivedDeterministicallyFromEveryMachineCode(): void
    {
        foreach (DependencySyncErrorCodes::all() as $errorCode) {
            $exception = DependencySyncException::forCode($errorCode);

            self::assertSame(
                \strtolower(
                    \str_replace('_', '-', $errorCode),
                ),
                $exception->reason(),
            );
        }
    }

    public function testRecoveryReceiptIdMatchesDocumentedGrammar(): void
    {
        foreach (
            [
                'A',
                'receipt_ABC-123',
                \str_repeat('a', 128),
            ] as $receiptId
        ) {
            $exception = DependencySyncException::forCode(
                DependencySyncErrorCodes::RECOVERY_REQUIRED,
                ['recoveryReceiptId' => $receiptId],
            );

            self::assertSame(
                $receiptId,
                $exception->context()['recoveryReceiptId'] ?? null,
            );
        }

        foreach (
            [
                '',
                'receipt.1',
                \str_repeat('a', 129),
            ] as $receiptId
        ) {
            try {
                DependencySyncException::forCode(
                    DependencySyncErrorCodes::RECOVERY_REQUIRED,
                    ['recoveryReceiptId' => $receiptId],
                );
                self::fail('Expected invalid recovery receipt id.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRejectsUnknownCodeUnknownContextCauseAndReceiptGrammar(): void
    {
        $cases = [
            static fn () => DependencySyncException::forCode('NOPE'),
            static fn () => DependencySyncException::forCode(
                DependencySyncErrorCodes::RECOVERY_REQUIRED,
                ['other' => 'x'],
            ),
            static fn () => DependencySyncException::forCode(
                DependencySyncErrorCodes::RECOVERY_REQUIRED,
                ['causeCode' => 'NOPE'],
            ),
            static fn () => DependencySyncException::forCode(
                DependencySyncErrorCodes::RECOVERY_REQUIRED,
                ['recoveryReceiptId' => 'bad value'],
            ),
        ];

        foreach ($cases as $case) {
            try {
                $case();
                self::fail('Expected invalid exception construction.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
