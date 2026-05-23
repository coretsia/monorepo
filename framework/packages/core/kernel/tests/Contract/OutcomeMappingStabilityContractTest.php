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

use Coretsia\Kernel\Runtime\Outcome;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OutcomeMappingStabilityContractTest extends TestCase
{
    public function testOutcomeVocabularyIsStable(): void
    {
        $reflection = new ReflectionClass(Outcome::class);

        self::assertTrue($reflection->isFinal());

        self::assertSame('success', Outcome::SUCCESS);
        self::assertSame('handled_error', Outcome::HANDLED_ERROR);
        self::assertSame('fatal_error', Outcome::FATAL_ERROR);

        self::assertSame(
            [
                Outcome::SUCCESS,
                Outcome::HANDLED_ERROR,
                Outcome::FATAL_ERROR,
            ],
            Outcome::values(),
        );

        self::assertTrue(Outcome::isValid(Outcome::SUCCESS));
        self::assertTrue(Outcome::isValid(Outcome::HANDLED_ERROR));
        self::assertTrue(Outcome::isValid(Outcome::FATAL_ERROR));

        self::assertFalse(Outcome::isValid(''));
        self::assertFalse(Outcome::isValid('SUCCESS'));
        self::assertFalse(Outcome::isValid('success '));
        self::assertFalse(Outcome::isValid('partial_success'));
        self::assertFalse(Outcome::isValid('error'));
    }

    #[DataProvider('httpOutcomeMappingProvider')]
    public function testHttpOutcomeMappingPolicyIsStable(
        int $httpStatus,
        string $expectedOutcome,
    ): void {
        self::assertSame(
            $expectedOutcome,
            self::mapHttpStatusForPolicySnapshot($httpStatus),
        );
    }

    /**
     * @return iterable<string, array{httpStatus: int, expectedOutcome: string}>
     */
    public static function httpOutcomeMappingProvider(): iterable
    {
        yield 'HTTP 200 maps to success' => [
            'httpStatus' => 200,
            'expectedOutcome' => Outcome::SUCCESS,
        ];

        yield 'HTTP 399 maps to success' => [
            'httpStatus' => 399,
            'expectedOutcome' => Outcome::SUCCESS,
        ];

        yield 'HTTP 400 maps to handled_error' => [
            'httpStatus' => 400,
            'expectedOutcome' => Outcome::HANDLED_ERROR,
        ];

        yield 'HTTP 500 maps to handled_error' => [
            'httpStatus' => 500,
            'expectedOutcome' => Outcome::HANDLED_ERROR,
        ];
    }

    #[DataProvider('cliOutcomeMappingProvider')]
    public function testCliOutcomeMappingPolicyIsStable(
        int $exitCode,
        string $expectedOutcome,
    ): void {
        self::assertSame(
            $expectedOutcome,
            self::mapCliExitCodeForPolicySnapshot($exitCode),
        );
    }

    /**
     * @return iterable<string, array{exitCode: int, expectedOutcome: string}>
     */
    public static function cliOutcomeMappingProvider(): iterable
    {
        yield 'CLI exit code 0 maps to success' => [
            'exitCode' => 0,
            'expectedOutcome' => Outcome::SUCCESS,
        ];

        yield 'CLI exit code 2 maps to handled_error' => [
            'exitCode' => 2,
            'expectedOutcome' => Outcome::HANDLED_ERROR,
        ];
    }

    public function testUncaughtExceptionOutcomePolicyIsStable(): void
    {
        self::assertSame(
            Outcome::FATAL_ERROR,
            self::mapUncaughtExceptionForPolicySnapshot(),
        );
    }

    public function testFatalErrorTakesPrecedenceOverHttpStatusMapping(): void
    {
        self::assertSame(
            Outcome::FATAL_ERROR,
            self::mapHttpStatusForPolicySnapshot(
                httpStatus: 200,
                uncaughtException: true,
            ),
        );

        self::assertSame(
            Outcome::FATAL_ERROR,
            self::mapHttpStatusForPolicySnapshot(
                httpStatus: 500,
                uncaughtException: true,
            ),
        );
    }

    public function testFatalErrorTakesPrecedenceOverCliExitCodeMapping(): void
    {
        self::assertSame(
            Outcome::FATAL_ERROR,
            self::mapCliExitCodeForPolicySnapshot(
                exitCode: 0,
                uncaughtException: true,
            ),
        );

        self::assertSame(
            Outcome::FATAL_ERROR,
            self::mapCliExitCodeForPolicySnapshot(
                exitCode: 2,
                uncaughtException: true,
            ),
        );
    }

    public function testCanonicalOutcomeMappingSnapshotIsStable(): void
    {
        self::assertSame(
            [
                [
                    'http_status' => 200,
                    'outcome' => Outcome::SUCCESS,
                ],
                [
                    'http_status' => 399,
                    'outcome' => Outcome::SUCCESS,
                ],
                [
                    'http_status' => 400,
                    'outcome' => Outcome::HANDLED_ERROR,
                ],
                [
                    'http_status' => 500,
                    'outcome' => Outcome::HANDLED_ERROR,
                ],
                [
                    'cli_exit_code' => 0,
                    'outcome' => Outcome::SUCCESS,
                ],
                [
                    'cli_exit_code' => 2,
                    'outcome' => Outcome::HANDLED_ERROR,
                ],
                [
                    'uncaught_exception' => true,
                    'outcome' => Outcome::FATAL_ERROR,
                ],
            ],
            self::canonicalOutcomeMappingSnapshot(),
        );
    }

    public function testOutcomeClassDoesNotOwnTransportMappingImplementation(): void
    {
        self::assertFalse(
            \method_exists(Outcome::class, 'fromHttpStatus'),
            'Outcome must remain a stable token vocabulary; HTTP mapping is adapter/runtime-owned.',
        );

        self::assertFalse(
            \method_exists(Outcome::class, 'fromExitCode'),
            'Outcome must remain a stable token vocabulary; CLI mapping is adapter/runtime-owned.',
        );
    }

    public function testOutcomePolicySsotContainsExactHttpCliRules(): void
    {
        $policy = self::outcomePolicyDocument();

        self::assertStringContainsString(
            '| status `< 400`     | `success`       |',
            $policy,
        );

        self::assertStringContainsString(
            '| status `>= 400`    | `handled_error` |',
            $policy,
        );

        self::assertStringContainsString(
            '| uncaught exception | `fatal_error`   |',
            $policy,
        );

        self::assertStringContainsString(
            '| `exitCode = 0`                              | `success`       |',
            $policy,
        );

        self::assertStringContainsString(
            '| `exitCode != 0` without uncaught exceptions | `handled_error` |',
            $policy,
        );

        self::assertStringContainsString(
            '| uncaught exception                          | `fatal_error`   |',
            $policy,
        );

        self::assertStringContainsString(
            '`fatal_error` MUST take precedence over status-code or exit-code based mapping.',
            $policy,
        );
    }

    private static function mapHttpStatusForPolicySnapshot(
        int $httpStatus,
        bool $uncaughtException = false,
    ): string {
        if ($uncaughtException) {
            return Outcome::FATAL_ERROR;
        }

        return $httpStatus < 400
            ? Outcome::SUCCESS
            : Outcome::HANDLED_ERROR;
    }

    private static function mapCliExitCodeForPolicySnapshot(
        int $exitCode,
        bool $uncaughtException = false,
    ): string {
        if ($uncaughtException) {
            return Outcome::FATAL_ERROR;
        }

        return $exitCode === 0
            ? Outcome::SUCCESS
            : Outcome::HANDLED_ERROR;
    }

    private static function mapUncaughtExceptionForPolicySnapshot(): string
    {
        return Outcome::FATAL_ERROR;
    }

    /**
     * @return list<array<string, bool|int|string>>
     */
    private static function canonicalOutcomeMappingSnapshot(): array
    {
        return [
            [
                'http_status' => 200,
                'outcome' => self::mapHttpStatusForPolicySnapshot(200),
            ],
            [
                'http_status' => 399,
                'outcome' => self::mapHttpStatusForPolicySnapshot(399),
            ],
            [
                'http_status' => 400,
                'outcome' => self::mapHttpStatusForPolicySnapshot(400),
            ],
            [
                'http_status' => 500,
                'outcome' => self::mapHttpStatusForPolicySnapshot(500),
            ],
            [
                'cli_exit_code' => 0,
                'outcome' => self::mapCliExitCodeForPolicySnapshot(0),
            ],
            [
                'cli_exit_code' => 2,
                'outcome' => self::mapCliExitCodeForPolicySnapshot(2),
            ],
            [
                'uncaught_exception' => true,
                'outcome' => self::mapUncaughtExceptionForPolicySnapshot(),
            ],
        ];
    }

    private static function outcomePolicyDocument(): string
    {
        $path = self::outcomePolicyDocumentPath();

        self::assertFileExists(
            $path,
            'docs/ssot/uow-outcome-policy.md must exist.',
        );

        $contents = \file_get_contents($path);

        if ($contents === false) {
            self::fail('docs/ssot/uow-outcome-policy.md must be readable.');
        }

        return $contents;
    }

    private static function outcomePolicyDocumentPath(): string
    {
        return \dirname(__DIR__, 6) . '/docs/ssot/uow-outcome-policy.md';
    }
}
