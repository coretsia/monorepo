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
use Coretsia\Foundation\Serialization\StableJsonDecoder;
use PHPUnit\Framework\TestCase;

final class StableJsonDecoderUsesJsonLikeNormalizerContractTest extends TestCase
{
    public function testNormalizesDecodedValuesThroughJsonLikeNormalizer(): void
    {
        self::assertSame(
            [
                'alpha' => [
                    'a' => 1,
                    'z' => 2,
                ],
                'items' => [
                    [
                        'a' => 1,
                        'b' => 2,
                    ],
                    'kept-in-list-order',
                    null,
                    true,
                    42,
                ],
                'zeta' => 'last',
            ],
            StableJsonDecoder::decodeStable(
                '{"zeta":"last","alpha":{"z":2,"a":1},"items":[{"b":2,"a":1},"kept-in-list-order",null,true,42]}',
            ),
        );
    }

    public function testNormalizationStageFailuresAreMappedToStableJsonFailures(): void
    {
        self::assertStableJsonDecoderInvalid(
            operation: static fn (): mixed => StableJsonDecoder::decodeStable(
                '{"safe":{"value":12.34}}',
            ),
            expectedPath: 'value.safe.value',
            expectedStableReason: 'stable-json-float-forbidden',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                '12.34',
                '{"safe":{"value":12.34}}',
            ],
        );
    }

    public function testConversionStageFailuresAreMappedToStableJsonFailures(): void
    {
        self::assertStableJsonDecoderInvalid(
            operation: static fn (): mixed => StableJsonDecoder::decodeStable(
                '{"0":"raw-json-payload-secret-token"}',
            ),
            expectedPath: 'value',
            expectedStableReason: 'stable-json-map-key-must-be-string',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'raw-json-payload-secret-token',
                '{"0":"raw-json-payload-secret-token"}',
            ],
        );
    }

    public function testRejectsAmbiguousJsonObjectKeysThatCannotBeSafelyRepresentedAsPhpStringMapKeys(): void
    {
        self::assertStableJsonDecoderInvalid(
            operation: static fn (): mixed => StableJsonDecoder::decodeStableMap(
                '{"0":"raw-zero-key-value"}',
            ),
            expectedPath: 'value',
            expectedStableReason: 'stable-json-map-key-must-be-string',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'raw-zero-key-value',
                '{"0":"raw-zero-key-value"}',
            ],
        );

        self::assertStableJsonDecoderInvalid(
            operation: static fn (): mixed => StableJsonDecoder::decodeStableMap(
                '{"-1":"raw-negative-key-value"}',
            ),
            expectedPath: 'value',
            expectedStableReason: 'stable-json-map-key-must-be-string',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'raw-negative-key-value',
                '{"-1":"raw-negative-key-value"}',
            ],
        );

        self::assertStableJsonDecoderInvalid(
            operation: static fn (): mixed => StableJsonDecoder::decodeStableMap(
                '{"42":"raw-integer-key-value"}',
            ),
            expectedPath: 'value',
            expectedStableReason: 'stable-json-map-key-must-be-string',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'raw-integer-key-value',
                '{"42":"raw-integer-key-value"}',
            ],
        );
    }

    public function testNestedAmbiguousJsonObjectKeysFailWithSafePathOnly(): void
    {
        self::assertStableJsonDecoderInvalid(
            operation: static fn (): mixed => StableJsonDecoder::decodeStable(
                '[{"safe":{"0":"raw-nested-secret-token"}}]',
            ),
            expectedPath: 'value[0].safe',
            expectedStableReason: 'stable-json-map-key-must-be-string',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'raw-nested-secret-token',
                '[{"safe":{"0":"raw-nested-secret-token"}}]',
            ],
        );
    }

    public function testJsonDecodeFailuresDoNotExposeRawJsonPayloads(): void
    {
        $rawJson = '{"safe":"raw-json-payload-secret-token"';

        try {
            StableJsonDecoder::decodeStable($rawJson);

            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('stable-json-decode-failed', $exception->getMessage());

            $previous = $exception->getPrevious();

            self::assertInstanceOf(\JsonException::class, $previous);

            self::assertStringNotContainsString($rawJson, $exception->getMessage());
            self::assertStringNotContainsString($rawJson, $previous->getMessage());
            self::assertStringNotContainsString('raw-json-payload-secret-token', $exception->getMessage());
            self::assertStringNotContainsString('raw-json-payload-secret-token', $previous->getMessage());
        }
    }

    public function testDecoderFailuresDoNotExposeUnsafeDiagnostics(): void
    {
        self::assertStableJsonDecoderInvalid(
            operation: static fn (): mixed => StableJsonDecoder::decodeStable(
                '{"0":"raw-json-payload-secret-token-/tmp/coretsia-secret"}',
            ),
            expectedPath: 'value',
            expectedStableReason: 'stable-json-map-key-must-be-string',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'raw-json-payload-secret-token-/tmp/coretsia-secret',
                '/tmp/coretsia-secret',
                'raw-json-payload-secret-token',
                'payload-secret-token',
                'stdClass',
                'resource',
                'php://memory',
                'ENV_SECRET_VALUE',
            ],
        );
    }

    /**
     * @param callable(): mixed $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertStableJsonDecoderInvalid(
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
                    'StableJsonDecoder diagnostics must not leak raw JSON payloads, rejected values, or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $previous->getMessage(),
                    'JsonLikeNormalizationException diagnostics must not leak raw JSON payloads, rejected values, or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $previous->path(),
                    'JsonLikeNormalizationException paths must not leak raw JSON payloads, rejected values, or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $previous->reason(),
                    'JsonLikeNormalizationException reasons must not leak raw JSON payloads, rejected values, or unsafe details.',
                );
            }
        }
    }
}
