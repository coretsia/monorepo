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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StableJsonEncoderUsesJsonLikeNormalizerContractTest extends TestCase
{
    public function testEncodesValidJsonLikeValuesIntoDeterministicStableBytes(): void
    {
        $payload = [
            'zeta' => 'last',
            'alpha' => [
                'z' => 'nested-last',
                'a' => 'nested-first',
            ],
            'items' => [
                [
                    'b' => 2,
                    'a' => 1,
                ],
                'kept-in-list-order',
                null,
                true,
                42,
            ],
            'slash' => '/docs/✓',
            'emptyList' => [],
        ];

        $expected = '{"alpha":{"a":"nested-first","z":"nested-last"},"emptyList":[],"items":[{"a":1,"b":2},"kept-in-list-order",null,true,42],"slash":"/docs/✓","zeta":"last"}' . "\n";

        self::assertSame($expected, StableJsonEncoder::encodeStable($payload));
        self::assertSame($expected, new StableJsonEncoder()->encode($payload));
    }

    public function testPreservesFinalLfForScalarPayloads(): void
    {
        self::assertSame('null' . "\n", StableJsonEncoder::encodeStable(null));
        self::assertSame('true' . "\n", StableJsonEncoder::encodeStable(true));
        self::assertSame('123' . "\n", StableJsonEncoder::encodeStable(123));
        self::assertSame('"safe-string"' . "\n", StableJsonEncoder::encodeStable('safe-string'));
    }

    #[DataProvider('floatProvider')]
    public function testFloatsFailThroughFoundationJsonLikePolicy(
        mixed $value,
        string $rawValueNeedle,
    ): void {
        self::assertStableJsonInvalid(
            operation: static fn (): string => StableJsonEncoder::encodeStable([
                'safe' => [
                    'value' => $value,
                ],
            ]),
            expectedPath: 'value.safe.value',
            expectedStableReason: 'stable-json-float-forbidden',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                $rawValueNeedle,
            ],
        );
    }

    /**
     * @return iterable<string, array{value: mixed, rawValueNeedle: string}>
     */
    public static function floatProvider(): iterable
    {
        yield 'finite float' => [
            'value' => 12.34,
            'rawValueNeedle' => '12.34',
        ];

        yield 'NaN' => [
            'value' => \NAN,
            'rawValueNeedle' => 'NAN',
        ];

        yield 'INF' => [
            'value' => \INF,
            'rawValueNeedle' => 'INF',
        ];

        yield '-INF' => [
            'value' => -\INF,
            'rawValueNeedle' => '-INF',
        ];
    }

    public function testObjectsFailThroughFoundationJsonLikePolicyWithPathAwareStableJsonReason(): void
    {
        $object = new class() {
            public string $secret = 'raw-object-secret-value';
        };

        self::assertStableJsonInvalid(
            operation: static fn (): string => StableJsonEncoder::encodeStable([
                'safe' => $object,
            ]),
            expectedPath: 'value.safe',
            expectedStableReason: 'stable-json-object-forbidden',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-object-secret-value',
                $object::class,
                'class@anonymous',
            ],
        );
    }

    public function testClosuresFailThroughFoundationJsonLikePolicyWithPathAwareStableJsonReason(): void
    {
        self::assertStableJsonInvalid(
            operation: static fn (): string => StableJsonEncoder::encodeStable([
                'safe' => static fn (): string => 'raw-closure-secret-value',
            ]),
            expectedPath: 'value.safe',
            expectedStableReason: 'stable-json-closure-forbidden',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-closure-secret-value',
                'Closure',
            ],
        );
    }

    public function testResourcesFailThroughFoundationJsonLikePolicyWithPathAwareStableJsonReason(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            self::assertStableJsonInvalid(
                operation: static fn (): string => StableJsonEncoder::encodeStable([
                    'safe' => $resource,
                ]),
                expectedPath: 'value.safe',
                expectedStableReason: 'stable-json-resource-forbidden',
                expectedJsonLikeReason: JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN,
                forbiddenDiagnosticsNeedles: [
                    'php://memory',
                ],
            );
        } finally {
            \fclose($resource);
        }
    }

    public function testNonStringMapKeysFailThroughFoundationJsonLikePolicyWithPathAwareStableJsonReason(): void
    {
        self::assertStableJsonInvalid(
            operation: static fn (): string => StableJsonEncoder::encodeStable([
                1 => 'integer-map-key',
            ]),
            expectedPath: 'value',
            expectedStableReason: 'stable-json-map-key-must-be-string',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'integer-map-key',
            ],
        );

        self::assertStableJsonInvalid(
            operation: static fn (): string => StableJsonEncoder::encodeStable([
                'safe' => [
                    1 => 'nested-integer-map-key',
                ],
            ]),
            expectedPath: 'value.safe',
            expectedStableReason: 'stable-json-map-key-must-be-string',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'nested-integer-map-key',
            ],
        );
    }

    public function testUnsupportedTypesFailThroughFoundationJsonLikePolicyWithPathAwareStableJsonReason(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        \fclose($resource);

        self::assertFalse(\is_resource($resource));
        self::assertSame('resource (closed)', \get_debug_type($resource));

        self::assertStableJsonInvalid(
            operation: static fn (): string => StableJsonEncoder::encodeStable([
                'safe' => $resource,
            ]),
            expectedPath: 'value.safe',
            expectedStableReason: 'stable-json-type-forbidden',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'resource (closed)',
                'php://memory',
            ],
        );
    }

    public function testUnsafeMapKeysUseFoundationSafePathInStableJsonFailure(): void
    {
        self::assertStableJsonInvalid(
            operation: static fn (): string => StableJsonEncoder::encodeStable([
                'safe' => [
                    'Authorization: Bearer raw-secret-token' => 1.25,
                ],
            ]),
            expectedPath: 'value.safe[<key>]',
            expectedStableReason: 'stable-json-float-forbidden',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'Authorization',
                'Bearer',
                'raw-secret-token',
            ],
        );
    }

    public function testJsonEncodeFailuresStillUseStableJsonEncodeFailedReason(): void
    {
        try {
            StableJsonEncoder::encodeStable([
                'invalidUtf8' => "\xB1\x31",
            ]);

            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('stable-json-encode-failed', $exception->getMessage());
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    /**
     * @param callable(): string $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertStableJsonInvalid(
        callable $operation,
        string $expectedPath,
        string $expectedStableReason,
        string $expectedJsonLikeReason,
        array $forbiddenDiagnosticsNeedles = [],
    ): void {
        try {
            $operation();
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString($expectedStableReason, $exception->getMessage());
            self::assertStringContainsString($expectedPath, $exception->getMessage());
            self::assertStringNotContainsString($expectedJsonLikeReason, $exception->getMessage());

            $previous = $exception->getPrevious();

            self::assertInstanceOf(JsonLikeNormalizationException::class, $previous);
            self::assertSame(JsonLikeNormalizationException::ERROR_CODE, $previous->errorCode());
            self::assertSame($expectedPath, $previous->path());
            self::assertSame($expectedJsonLikeReason, $previous->reason());

            foreach ($forbiddenDiagnosticsNeedles as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $exception->getMessage(),
                    'StableJsonEncoder diagnostics must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $previous->getMessage(),
                    'JsonLikeNormalizationException diagnostics must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $previous->path(),
                    'JsonLikeNormalizationException paths must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $previous->reason(),
                    'JsonLikeNormalizationException reasons must not leak rejected raw values or unsafe details.',
                );
            }
        }
    }
}
