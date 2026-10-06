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

use Coretsia\Contracts\Kernel\Ops\OpsResult;
use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class KernelOpsFacadeReturnsJsonLikeResultsTest extends TestCase
{
    public function testFacadeProducesDeepDeterministicJsonLikeData(): void
    {
        $result = self::successResult(
            [
                'zeta' => 9,
                'alpha' => [
                    'zeta' => 'last',
                    'list' => [
                        'third',
                        null,
                        true,
                        false,
                        7,
                        'first-order-is-preserved',
                    ],
                    'alpha' => [
                        'zeta' => 2,
                        'alpha' => 1,
                    ],
                ],
                'middle' => [
                    'string' => 'safe-value',
                    'null' => null,
                    'int' => 42,
                    'bool' => true,
                ],
            ],
        );

        self::assertSame(
            [
                'alpha' => [
                    'alpha' => [
                        'alpha' => 1,
                        'zeta' => 2,
                    ],
                    'list' => [
                        'third',
                        null,
                        true,
                        false,
                        7,
                        'first-order-is-preserved',
                    ],
                    'zeta' => 'last',
                ],
                'middle' => [
                    'bool' => true,
                    'int' => 42,
                    'null' => null,
                    'string' => 'safe-value',
                ],
                'zeta' => 9,
            ],
            $result->data(),
        );

        self::assertJsonLike($result->data());
    }

    public function testFacadeRejectsFloatAsHardFailure(): void
    {
        self::assertNormalizationRejected(
            data: [
                'nested' => [
                    'value' => 1.25,
                ],
            ],
            expectedReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
        );
    }

    public function testFacadeRejectsObjectsAndResources(): void
    {
        self::assertNormalizationRejected(
            data: [
                'nested' => [
                    'value' => new \stdClass(),
                ],
            ],
            expectedReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
        );

        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            self::assertNormalizationRejected(
                data: [
                    'nested' => [
                        'value' => $resource,
                    ],
                ],
                expectedReason: JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN,
            );
        } finally {
            \fclose($resource);
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function successResult(array $data): OpsResult
    {
        $facade = new ReflectionClass(KernelOpsFacade::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(KernelOpsFacade::class, 'successResult');
        $result = $method->invoke(
            $facade,
            'config.validate',
            'console',
            'test',
            $data,
        );

        self::assertInstanceOf(OpsResult::class, $result);

        return $result;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function assertNormalizationRejected(
        array $data,
        string $expectedReason,
    ): void {
        try {
            self::successResult($data);

            self::fail('Expected non-json-like Kernel Ops result data to be rejected.');
        } catch (JsonLikeNormalizationException $exception) {
            self::assertSame($expectedReason, $exception->reason());
        }
    }

    private static function assertJsonLike(mixed $value): void
    {
        if (
            $value === null
            || \is_bool($value)
            || \is_int($value)
            || \is_string($value)
        ) {
            return;
        }

        self::assertIsArray($value);
        self::assertFalse(\is_float($value));
        self::assertFalse(\is_object($value));
        self::assertFalse(\is_resource($value));

        if (\array_is_list($value)) {
            foreach ($value as $item) {
                self::assertJsonLike($item);
            }

            return;
        }

        $keys = \array_keys($value);
        $sortedKeys = $keys;

        \usort(
            $sortedKeys,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        self::assertSame(
            $sortedKeys,
            $keys,
            'Kernel Ops result maps must be recursively strcmp-sorted by the producer.',
        );

        foreach ($value as $item) {
            self::assertJsonLike($item);
        }
    }
}
