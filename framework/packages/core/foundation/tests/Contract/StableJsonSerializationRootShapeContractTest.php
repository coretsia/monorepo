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

use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use PHPUnit\Framework\TestCase;

final class StableJsonSerializationRootShapeContractTest extends TestCase
{
    public function testGenericEncoderAndDecoderEntryPointsAreShapeInsensitiveAtRoot(): void
    {
        self::assertSame("[]\n", StableJsonEncoder::encodeStable([]));

        self::assertSame([], StableJsonDecoder::decodeStable('{}'));
        self::assertSame([], StableJsonDecoder::decodeStable('[]'));
    }

    public function testEncoderRootShapeEntryPointsPreserveEmptyRootShape(): void
    {
        self::assertSame("{}\n", StableJsonEncoder::encodeStableMap([]));
        self::assertSame("[]\n", StableJsonEncoder::encodeStableList([]));
    }

    public function testDecoderRootShapeEntryPointsPreserveEmptyRootShape(): void
    {
        self::assertSame([], StableJsonDecoder::decodeStableMap('{}'));
        self::assertSame([], StableJsonDecoder::decodeStableList('[]'));
    }

    public function testRootMapEncoderRejectsNonMapRootValues(): void
    {
        self::assertStableJsonRootFailure(
            operation: static fn (): string => StableJsonEncoder::encodeStableMap(null),
            expectedMessage: 'stable-json-root-map-required',
        );

        self::assertStableJsonRootFailure(
            operation: static fn (): string => StableJsonEncoder::encodeStableMap('safe-string'),
            expectedMessage: 'stable-json-root-map-required',
        );

        self::assertStableJsonRootFailure(
            operation: static fn (): string => StableJsonEncoder::encodeStableMap(['list-item']),
            expectedMessage: 'stable-json-root-map-required',
        );

        self::assertStableJsonRootFailure(
            operation: static fn (): string => StableJsonEncoder::encodeStableMap(new \stdClass()),
            expectedMessage: 'stable-json-root-map-required',
            forbiddenDiagnosticsNeedles: [
                'stdClass',
            ],
        );
    }

    public function testRootListEncoderRejectsNonListRootValues(): void
    {
        self::assertStableJsonRootFailure(
            operation: static fn (): string => StableJsonEncoder::encodeStableList(null),
            expectedMessage: 'stable-json-root-list-required',
        );

        self::assertStableJsonRootFailure(
            operation: static fn (): string => StableJsonEncoder::encodeStableList('safe-string'),
            expectedMessage: 'stable-json-root-list-required',
        );

        self::assertStableJsonRootFailure(
            operation: static fn (): string => StableJsonEncoder::encodeStableList(['version' => 1]),
            expectedMessage: 'stable-json-root-list-required',
        );

        self::assertStableJsonRootFailure(
            operation: static fn (): string => StableJsonEncoder::encodeStableList(new \stdClass()),
            expectedMessage: 'stable-json-root-list-required',
            forbiddenDiagnosticsNeedles: [
                'stdClass',
            ],
        );
    }

    public function testRootMapDecoderRejectsJsonArrayRootBeforeGenericNormalization(): void
    {
        self::assertStableJsonRootFailure(
            operation: static fn (): array => StableJsonDecoder::decodeStableMap('[]'),
            expectedMessage: 'stable-json-root-map-required',
        );
    }

    public function testRootListDecoderRejectsJsonObjectRootBeforeGenericNormalization(): void
    {
        self::assertStableJsonRootFailure(
            operation: static fn (): array => StableJsonDecoder::decodeStableList('{}'),
            expectedMessage: 'stable-json-root-list-required',
        );
    }

    public function testRootObjectListDistinctionIsEnforcedBeforeEmptyShapesNormalizeToPhpArray(): void
    {
        self::assertSame([], StableJsonDecoder::decodeStable('{}'));
        self::assertSame([], StableJsonDecoder::decodeStable('[]'));

        self::assertSame([], StableJsonDecoder::decodeStableMap('{}'));
        self::assertSame([], StableJsonDecoder::decodeStableList('[]'));

        self::assertStableJsonRootFailure(
            operation: static fn (): array => StableJsonDecoder::decodeStableMap('[]'),
            expectedMessage: 'stable-json-root-map-required',
        );

        self::assertStableJsonRootFailure(
            operation: static fn (): array => StableJsonDecoder::decodeStableList('{}'),
            expectedMessage: 'stable-json-root-list-required',
        );
    }

    public function testNestedEmptyObjectListDistinctionIsNotAssertedByBaselineSerializationContract(): void
    {
        self::assertSame(
            [
                'list' => [],
                'object' => [],
            ],
            StableJsonDecoder::decodeStable('{"object":{},"list":[]}'),
        );

        self::assertSame(
            '{"list":[],"object":[]}' . "\n",
            StableJsonEncoder::encodeStable([
                'object' => [],
                'list' => [],
            ]),
        );
    }

    public function testStableJsonEncoderFailuresDoNotExposeUnsafeDiagnostics(): void
    {
        $object = new class() {
            public string $secret = 'raw-encoder-secret-token-/tmp/coretsia-secret';
        };

        self::assertStableJsonRootFailure(
            operation: static fn (): string => StableJsonEncoder::encodeStable([
                'safe' => $object,
                'payload' => 'raw-payload-fragment',
                'environment' => 'ENV_SECRET_VALUE',
            ]),
            expectedMessage: 'stable-json-object-forbidden: value at value.safe',
            forbiddenDiagnosticsNeedles: [
                'raw-encoder-secret-token-/tmp/coretsia-secret',
                '/tmp/coretsia-secret',
                'raw-payload-fragment',
                'ENV_SECRET_VALUE',
                $object::class,
                'class@anonymous',
                'stdClass',
                'php://memory',
            ],
        );
    }

    public function testStableJsonDecoderFailuresDoNotExposeUnsafeDiagnostics(): void
    {
        self::assertStableJsonRootFailure(
            operation: static fn (): mixed => StableJsonDecoder::decodeStable(
                '{"0":"raw-decoder-json-payload-secret-token-/tmp/coretsia-secret"}',
            ),
            expectedMessage: 'stable-json-map-key-must-be-string: value at value',
            forbiddenDiagnosticsNeedles: [
                '{"0":"raw-decoder-json-payload-secret-token-/tmp/coretsia-secret"}',
                'raw-decoder-json-payload-secret-token-/tmp/coretsia-secret',
                '/tmp/coretsia-secret',
                'raw-decoder-json-payload-secret-token',
                'payload-secret-token',
                'stdClass',
                'resource',
                'php://memory',
                'ENV_SECRET_VALUE',
            ],
        );
    }

    public function testStableJsonEncoderResourceFailuresDoNotExposeResourceIds(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            self::assertStableJsonRootFailure(
                operation: static fn (): string => StableJsonEncoder::encodeStable([
                    'handle' => $resource,
                ]),
                expectedMessage: 'stable-json-resource-forbidden: value at value.handle',
                forbiddenDiagnosticsNeedles: [
                    'Resource id',
                    'stream',
                    'php://memory',
                ],
            );
        } finally {
            \fclose($resource);
        }
    }

    /**
     * @param callable(): mixed $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertStableJsonRootFailure(
        callable $operation,
        string $expectedMessage,
        array $forbiddenDiagnosticsNeedles = [],
    ): void {
        try {
            $operation();

            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());

            foreach ($forbiddenDiagnosticsNeedles as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $exception->getMessage(),
                    'Stable JSON root-shape diagnostics must not leak raw values, payloads, class names, paths, or unsafe details.',
                );

                if ($exception->getPrevious() !== null) {
                    self::assertStringNotContainsString(
                        $needle,
                        $exception->getPrevious()->getMessage(),
                        'Previous stable JSON diagnostic exception must not leak raw values, payloads, class names, paths, or unsafe details.',
                    );
                }
            }
        }
    }
}
