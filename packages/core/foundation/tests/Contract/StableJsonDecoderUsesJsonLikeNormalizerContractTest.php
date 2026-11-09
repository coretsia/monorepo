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

    public function testStrictStringMapsRejectDuplicateAndEscapedEquivalentObjectKeys(): void
    {
        foreach (
            [
                '{"web":"micro","web":"express"}',
                '{"web":"micro","w\\u0065b":"express"}',
                '{"web":["core.kernel"],"web":[]}',
            ] as $json
        ) {
            $operation = \str_contains($json, '[')
                ? static fn (): array => StableJsonDecoder::decodeStrictStringListMap($json)
                : static fn (): array => StableJsonDecoder::decodeStrictStringMap($json);

            self::assertStrictJsonFailure($operation, 'stable-json-duplicate-object-key');
        }
    }

    public function testStrictMapsRejectNestedDuplicateObjectKeysBeforeValueShapeValidation(): void
    {
        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringListMap(
                '{"web":{"hidden":"secret","hidden":"replaced"}}',
            ),
            'stable-json-duplicate-object-key',
        );

        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringMap(
                '{"web":{"hidden":1,"h\\u0069dden":2}}',
            ),
            'stable-json-duplicate-object-key',
        );
    }

    public function testStrictMapsPreserveRootAndNestedJsonShapes(): void
    {
        self::assertSame([], StableJsonDecoder::decodeStrictStringMap('{}'));
        self::assertSame([], StableJsonDecoder::decodeStrictStringListMap('{}'));
        self::assertSame(['web' => []], StableJsonDecoder::decodeStrictStringListMap('{"web":[]}'));
        self::assertSame(
            ['api' => 'express', 'web' => 'micro'],
            StableJsonDecoder::decodeStrictStringMap('{"web":"micro","api":"express"}'),
        );

        foreach (['[]', '["web"]', 'null'] as $json) {
            self::assertStrictJsonFailure(
                static fn (): array => StableJsonDecoder::decodeStrictStringMap($json),
                'stable-json-root-map-required',
            );
        }

        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringListMap('{"web":{}}'),
            'stable-json-string-list-required',
        );
        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringMap('{"web":[]}'),
            'stable-json-string-value-required',
        );
        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringListMap('{"web":[1]}'),
            'stable-json-string-list-required',
        );
    }

    public function testStrictJsonMapsEnforceInputDepthAndObjectKeyBudgets(): void
    {
        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringMap(
                '{"web":"' . \str_repeat('x', 65_536) . '"}',
            ),
            'stable-json-bounds-invalid',
        );
        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringMap(
                '{"web":' . \str_repeat('[', 65) . '"x"' . \str_repeat(']', 65) . '}',
            ),
            'stable-json-decode-failed',
        );

        $pairs = [];

        for ($index = 0; $index < 4_097; $index++) {
            $pairs[] = '"key' . $index . '":"x"';
        }

        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringMap('{' . \implode(',', $pairs) . '}'),
            'stable-json-key-count-invalid',
        );
    }

    public function testStrictStringListMapsPreserveValuesAndEnforceSharedBounds(): void
    {
        self::assertSame(
            ['web' => ['platform.worker', 'core.kernel']],
            StableJsonDecoder::decodeStrictStringListMap(
                '{"web":["platform.worker","core.kernel"]}',
            ),
        );

        foreach (['[]', '["web"]', 'null'] as $json) {
            self::assertStrictJsonFailure(
                static fn (): array => StableJsonDecoder::decodeStrictStringListMap($json),
                'stable-json-root-map-required',
            );
        }

        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringListMap(
                '{"web":["core.kernel"],"w\\u0065b":[]}',
            ),
            'stable-json-duplicate-object-key',
        );
        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringListMap(
                '{"web":["' . \str_repeat('x', 65_536) . '"]}',
            ),
            'stable-json-bounds-invalid',
        );
        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringListMap(
                '{"web":' . \str_repeat('[', 65) . '"x"' . \str_repeat(']', 65) . '}',
            ),
            'stable-json-decode-failed',
        );

        $pairs = [];

        for ($index = 0; $index < 4_097; $index++) {
            $pairs[] = '"key' . $index . '":[]';
        }

        self::assertStrictJsonFailure(
            static fn (): array => StableJsonDecoder::decodeStrictStringListMap('{' . \implode(',', $pairs) . '}'),
            'stable-json-key-count-invalid',
        );
    }

    public function testStrictJsonOptInDoesNotChangeGenericDecodingContracts(): void
    {
        self::assertSame([], StableJsonDecoder::decodeStable('{}'));
        self::assertSame([], StableJsonDecoder::decodeStable('[]'));
        self::assertSame([], StableJsonDecoder::decodeStableMap('{}'));
        self::assertSame([], StableJsonDecoder::decodeStableList('[]'));
        self::assertSame(
            ['web' => []],
            StableJsonDecoder::decodeStableMap('{"web":{}}'),
        );
        self::assertSame(
            ['web' => 'express'],
            StableJsonDecoder::decodeStableMap('{"web":"micro","web":"express"}'),
        );
    }

    /** @param callable(): mixed $operation */
    private static function assertStrictJsonFailure(callable $operation, string $expectedReason): void
    {
        try {
            $operation();
            self::fail('Expected strict JSON-map validation to reject the input.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($expectedReason, $exception->getMessage());
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertStringNotContainsString('replaced', $exception->getMessage());
        }
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
