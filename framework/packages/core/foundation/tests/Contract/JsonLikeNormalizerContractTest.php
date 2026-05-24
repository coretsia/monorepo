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
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonLikeNormalizerContractTest extends TestCase
{
    public function testAcceptsJsonLikeScalars(): void
    {
        self::assertNull(JsonLikeNormalizer::normalize(null));
        self::assertTrue(JsonLikeNormalizer::normalize(true));
        self::assertFalse(JsonLikeNormalizer::normalize(false));
        self::assertSame(123, JsonLikeNormalizer::normalize(123));
        self::assertSame('safe-string', JsonLikeNormalizer::normalize('safe-string'));
    }

    #[DataProvider('floatProvider')]
    public function testRejectsFloatsNaNAndInfinity(mixed $value, string $rawValueNeedle): void
    {
        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => [
                    'value' => $value,
                ],
            ]),
            expectedPath: 'value.safe.value',
            expectedReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
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

    public function testRejectsObjectsWithoutLeakingObjectDetails(): void
    {
        $object = new class() {
            public string $secret = 'raw-object-secret-value';
        };

        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => $object,
            ]),
            expectedPath: 'value.safe',
            expectedReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-object-secret-value',
                $object::class,
                'class@anonymous',
            ],
        );
    }

    public function testRejectsStringableObjectsAsObjectsWithoutLeakingObjectDetails(): void
    {
        $object = new class() {
            public function __toString(): string
            {
                return 'raw-stringable-secret-value';
            }
        };

        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => $object,
            ]),
            expectedPath: 'value.safe',
            expectedReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-stringable-secret-value',
                $object::class,
                'class@anonymous',
            ],
        );
    }

    public function testRejectsEnumObjectsAsObjectsWithoutLeakingEnumDetails(): void
    {
        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => JsonLikeNormalizerContractFixtureEnum::SECRET,
            ]),
            expectedPath: 'value.safe',
            expectedReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                JsonLikeNormalizerContractFixtureEnum::class,
                JsonLikeNormalizerContractFixtureEnum::SECRET->value,
            ],
        );
    }

    public function testRejectsClosuresBeforeGenericObjectRejection(): void
    {
        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => static fn (): string => 'raw-closure-secret-value',
            ]),
            expectedPath: 'value.safe',
            expectedReason: JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-closure-secret-value',
                'Closure',
            ],
        );
    }

    public function testRejectsResourcesWithoutLeakingResourceDetails(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            self::assertJsonLikeInvalid(
                operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                    'safe' => $resource,
                ]),
                expectedPath: 'value.safe',
                expectedReason: JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN,
                forbiddenDiagnosticsNeedles: [
                    'php://memory',
                ],
            );
        } finally {
            \fclose($resource);
        }
    }

    public function testRejectsUnsupportedClosedResourceTypeWithoutLeakingDetails(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        \fclose($resource);

        self::assertFalse(\is_resource($resource));
        self::assertSame('resource (closed)', \get_debug_type($resource));

        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => $resource,
            ]),
            expectedPath: 'value.safe',
            expectedReason: JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'resource (closed)',
                'php://memory',
            ],
        );
    }

    public function testRejectsNonStringMapKeys(): void
    {
        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                1 => 'integer-map-key',
            ]),
            expectedPath: 'value',
            expectedReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'integer-map-key',
            ],
        );

        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => [
                    1 => 'nested-integer-map-key',
                ],
            ]),
            expectedPath: 'value.safe',
            expectedReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'nested-integer-map-key',
            ],
        );
    }

    public function testNormalizesMapsRecursivelyByByteOrderAndPreservesListOrder(): void
    {
        self::assertSame(
            [
                'Alpha' => [
                    'Beta' => 2,
                    'alpha' => 1,
                ],
                'alpha' => [
                    [
                        'a' => 1,
                        'b' => 2,
                    ],
                    'kept-in-list-order',
                    [
                        'A' => 1,
                        'a' => 2,
                    ],
                ],
                'zeta' => true,
            ],
            JsonLikeNormalizer::normalize([
                'zeta' => true,
                'alpha' => [
                    [
                        'b' => 2,
                        'a' => 1,
                    ],
                    'kept-in-list-order',
                    [
                        'a' => 2,
                        'A' => 1,
                    ],
                ],
                'Alpha' => [
                    'alpha' => 1,
                    'Beta' => 2,
                ],
            ]),
        );
    }

    public function testPreservesEmptyArrayAsEmptyArray(): void
    {
        self::assertSame([], JsonLikeNormalizer::normalize([]));

        self::assertSame(
            [
                'empty' => [],
                'list' => [
                    [],
                ],
            ],
            JsonLikeNormalizer::normalize([
                'list' => [
                    [],
                ],
                'empty' => [],
            ]),
        );
    }

    public function testExceptionExposesErrorCodePathAndReason(): void
    {
        try {
            JsonLikeNormalizer::normalize([
                'safe' => [
                    'value' => 1.25,
                ],
            ]);

            self::fail('Expected JsonLikeNormalizationException was not thrown.');
        } catch (JsonLikeNormalizationException $exception) {
            self::assertSame(
                JsonLikeNormalizationException::ERROR_CODE,
                $exception->errorCode(),
            );

            self::assertSame(
                'CORETSIA_JSON_LIKE_INVALID',
                $exception->errorCode(),
            );

            self::assertSame('value.safe.value', $exception->path());
            self::assertSame(JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN, $exception->reason());

            self::assertStringContainsString(JsonLikeNormalizationException::ERROR_CODE, $exception->getMessage());
            self::assertStringContainsString(
                JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
                $exception->getMessage()
            );
            self::assertStringContainsString('value.safe.value', $exception->getMessage());
        }
    }

    public function testFailureDiagnosticsDoNotLeakRawValuesSecretsSqlObjectDetailsResourcesOrLocalPaths(): void
    {
        $object = new class() {
            public string $secret = 'object-secret-value';
        };

        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => $object,
            ]),
            expectedPath: 'value.safe',
            expectedReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'object-secret-value',
                $object::class,
                'class@anonymous',
                'SELECT * FROM users',
                'password = "secret-value"',
                'secret-value',
                __DIR__,
                \dirname(__DIR__, 2),
            ],
        );
    }

    #[DataProvider('unsafeKeyProvider')]
    public function testUnsafeMapKeysAreNotLeakedInDiagnosticPath(
        string $key,
        string $expectedPath,
        array $forbiddenPathNeedles,
    ): void {
        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => [
                    $key => 1.25,
                ],
            ]),
            expectedPath: 'value.safe' . $expectedPath,
            expectedReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: $forbiddenPathNeedles,
        );
    }

    /**
     * @return iterable<string, array{key: string, expectedPath: string, forbiddenPathNeedles: list<string>}>
     */
    public static function unsafeKeyProvider(): iterable
    {
        yield 'empty key' => [
            'key' => '',
            'expectedPath' => '[<empty-key>]',
            'forbiddenPathNeedles' => [],
        ];

        yield 'token key' => [
            'key' => 'Authorization: Bearer raw-secret-token',
            'expectedPath' => '[<key>]',
            'forbiddenPathNeedles' => [
                'Authorization',
                'Bearer',
                'raw-secret-token',
            ],
        ];

        yield 'sql key' => [
            'key' => 'SELECT * FROM users WHERE password = secret',
            'expectedPath' => '[<key>]',
            'forbiddenPathNeedles' => [
                'SELECT * FROM users',
                'password',
                'secret',
            ],
        ];

        yield 'control character key' => [
            'key' => "bad\nkey",
            'expectedPath' => '[<key>]',
            'forbiddenPathNeedles' => [
                "bad\nkey",
                'bad',
            ],
        ];

        yield 'url key' => [
            'key' => 'https://example.test/token',
            'expectedPath' => '[<key>]',
            'forbiddenPathNeedles' => [
                'https://example.test/token',
                'example.test',
            ],
        ];

        yield 'absolute path key' => [
            'key' => '/home/user/project/.env',
            'expectedPath' => '[<key>]',
            'forbiddenPathNeedles' => [
                '/home/user/project/.env',
                '/home/user',
                '.env',
            ],
        ];

        yield 'long key' => [
            'key' => 'a' . \str_repeat('b', 64),
            'expectedPath' => '[<key>]',
            'forbiddenPathNeedles' => [
                'abbbbb',
            ],
        ];
    }

    public function testInvalidRootPathIsSanitized(): void
    {
        self::assertJsonLikeInvalid(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize(
                [
                    'safe' => 1.25,
                ],
                'Authorization: Bearer raw-root-token',
            ),
            expectedPath: '[<path>].safe',
            expectedReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'Authorization',
                'Bearer',
                'raw-root-token',
            ],
        );
    }

    public function testReasonConstantsExposeStableTokenVocabulary(): void
    {
        self::assertSame('json-like-float-forbidden', JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN);
        self::assertSame('json-like-resource-forbidden', JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN);
        self::assertSame('json-like-closure-forbidden', JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN);
        self::assertSame('json-like-object-forbidden', JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN);
        self::assertSame(
            'json-like-map-key-must-be-string',
            JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING
        );
        self::assertSame('json-like-type-forbidden', JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN);
    }

    /**
     * @param callable(): mixed $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertJsonLikeInvalid(
        callable $operation,
        string $expectedPath,
        string $expectedReason,
        array $forbiddenDiagnosticsNeedles = [],
    ): void {
        try {
            $operation();
            self::fail('Expected JsonLikeNormalizationException was not thrown.');
        } catch (JsonLikeNormalizationException $exception) {
            self::assertSame(
                JsonLikeNormalizationException::ERROR_CODE,
                $exception->errorCode(),
            );

            self::assertSame($expectedPath, $exception->path());
            self::assertSame($expectedReason, $exception->reason());

            self::assertStringContainsString(JsonLikeNormalizationException::ERROR_CODE, $exception->getMessage());
            self::assertStringContainsString($expectedReason, $exception->getMessage());
            self::assertStringContainsString($expectedPath, $exception->getMessage());

            foreach ($forbiddenDiagnosticsNeedles as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $exception->getMessage(),
                    'JsonLikeNormalizer diagnostics must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $exception->path(),
                    'JsonLikeNormalizer paths must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $exception->reason(),
                    'JsonLikeNormalizer reasons must not leak rejected raw values or unsafe details.',
                );
            }
        }
    }
}

enum JsonLikeNormalizerContractFixtureEnum: string
{
    case SECRET = 'raw-enum-secret-value';
}
