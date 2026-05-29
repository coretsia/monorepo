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

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Kernel\Runtime\Hook\HookContextNormalizer;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkResult;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class HookContextNormalizerNormalizesErrorDescriptorTest extends TestCase
{
    public function testNormalizeResultExportsInternalErrorDescriptorAsJsonLikeErrorMap(): void
    {
        $error = new ErrorDescriptor(
            code: 'CORETSIA_TEST_ERROR',
            message: 'Kernel test failure',
            extensions: [
                'zeta' => 2,
                'alpha' => [
                    'nested' => true,
                ],
            ],
        );

        $result = new UnitOfWorkResult(
            uowId: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            type: self::validUnitOfWorkType(),
            correlationId: '01BX5ZZKBKACTAV9WEVGEMMVRZ',
            startedAt: 1000,
            finishedAt: 1017,
            durationMs: 17,
            outcome: self::validOutcome(),
            error: $error,
            extensions: [
                'zeta' => 2,
                'alpha' => [
                    'nested' => true,
                ],
            ],
        );

        self::assertSame($error, $result->error());

        $normalized = HookContextNormalizer::normalizeResult($result);

        self::assertArrayHasKey('error', $normalized);
        self::assertIsArray($normalized['error']);

        self::assertSame(
            [
                'code' => 'CORETSIA_TEST_ERROR',
                'extensions' => [
                    'alpha' => [
                        'nested' => true,
                    ],
                    'zeta' => 2,
                ],
                'httpStatus' => null,
                'message' => 'Kernel test failure',
                'schemaVersion' => ErrorDescriptor::SCHEMA_VERSION,
                'severity' => 'error',
            ],
            $normalized['error'],
        );

        self::assertSame(
            [
                'alpha' => [
                    'nested' => true,
                ],
                'zeta' => 2,
            ],
            $normalized['extensions'],
        );

        self::assertNoObjectInstancesRemain($normalized);
    }

    public function testNormalizeResultWithoutErrorOmitsErrorKey(): void
    {
        $result = new UnitOfWorkResult(
            uowId: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            type: self::validUnitOfWorkType(),
            correlationId: '01BX5ZZKBKACTAV9WEVGEMMVRZ',
            startedAt: 1000,
            finishedAt: 1017,
            durationMs: 17,
            outcome: self::validOutcome(),
            error: null,
            extensions: [
                'alpha' => true,
            ],
        );

        $normalized = HookContextNormalizer::normalizeResult($result);

        self::assertArrayNotHasKey('error', $normalized);
        self::assertNoObjectInstancesRemain($normalized);
    }

    /**
     * @param array<int|string,mixed> $payload
     */
    private static function assertNoObjectInstancesRemain(array $payload): void
    {
        foreach ($payload as $value) {
            self::assertFalse(
                \is_object($value),
                'Normalized hook payload must not contain object instances.',
            );

            if (\is_array($value)) {
                self::assertNoObjectInstancesRemain($value);
            }
        }
    }

    private static function validUnitOfWorkType(): string
    {
        foreach (self::publicStringConstants(UnitOfWorkType::class) as $value) {
            if (UnitOfWorkType::isValid($value)) {
                return $value;
            }
        }

        foreach (
            [
                'http.request',
                'cli.command',
                'worker.job',
                'scheduler.task',
                'test.unit',
            ] as $candidate
        ) {
            if (UnitOfWorkType::isValid($candidate)) {
                return $candidate;
            }
        }

        self::fail('Could not resolve a valid UnitOfWorkType value for test.');
    }

    private static function validOutcome(): string
    {
        foreach (self::publicStringConstants(Outcome::class) as $value) {
            if (Outcome::isValid($value)) {
                return $value;
            }
        }

        foreach (
            [
                'success',
                'failed',
                'failure',
                'ok',
                'error',
            ] as $candidate
        ) {
            if (Outcome::isValid($candidate)) {
                return $candidate;
            }
        }

        self::fail('Could not resolve a valid Outcome value for test.');
    }

    /**
     * @param class-string $className
     *
     * @return list<string>
     * @throws \ReflectionException
     */
    private static function publicStringConstants(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $values = [];

        foreach ($reflection->getReflectionConstants() as $constant) {
            if (!$constant->isPublic()) {
                continue;
            }

            $value = $constant->getValue();

            if (\is_string($value)) {
                $values[] = $value;
            }
        }

        \sort($values, \SORT_STRING);

        return $values;
    }
}
