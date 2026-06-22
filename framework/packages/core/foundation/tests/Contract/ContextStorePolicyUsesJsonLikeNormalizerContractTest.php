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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStorePolicy;
use Coretsia\Foundation\Context\Exception\ContextInvalidKeyException;
use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;
use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use PHPUnit\Framework\TestCase;

final class ContextStorePolicyUsesJsonLikeNormalizerContractTest extends TestCase
{
    public function testAssertValueDelegatesFloatValidationThroughJsonLikeNormalizer(): void
    {
        self::assertContextWriteForbidden(
            operation: static fn (): mixed => new ContextStorePolicy()->assertValue(
                [
                    'safe' => [
                        'value' => 12.34,
                    ],
                ],
                ContextKeys::CORRELATION_ID,
            ),
            expectedPath: 'correlation_id.safe.value',
            expectedContextReason: 'context-write-forbidden-float',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                '12.34',
            ],
        );
    }

    public function testAssertValueMapsClosureViolationToExistingContextReason(): void
    {
        self::assertContextWriteForbidden(
            operation: static fn (): mixed => new ContextStorePolicy()->assertValue(
                [
                    'safe' => static fn (): string => 'raw-closure-secret-value',
                ],
                ContextKeys::CORRELATION_ID,
            ),
            expectedPath: 'correlation_id.safe',
            expectedContextReason: 'context-write-forbidden-closure',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-closure-secret-value',
                'Closure',
            ],
        );
    }

    public function testAssertValueMapsObjectViolationToExistingContextReason(): void
    {
        $object = new class() {
            public string $secret = 'raw-object-secret-value';
        };

        self::assertContextWriteForbidden(
            operation: static fn (): mixed => new ContextStorePolicy()->assertValue(
                [
                    'safe' => $object,
                ],
                ContextKeys::CORRELATION_ID,
            ),
            expectedPath: 'correlation_id.safe',
            expectedContextReason: 'context-write-forbidden-object',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-object-secret-value',
                $object::class,
                'class@anonymous',
            ],
        );
    }

    public function testAssertValueMapsResourceViolationToExistingContextReason(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            self::assertContextWriteForbidden(
                operation: static fn (): mixed => new ContextStorePolicy()->assertValue(
                    [
                        'safe' => $resource,
                    ],
                    ContextKeys::CORRELATION_ID,
                ),
                expectedPath: 'correlation_id.safe',
                expectedContextReason: 'context-write-forbidden-resource',
                expectedJsonLikeReason: JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN,
                forbiddenDiagnosticsNeedles: [
                    'php://memory',
                ],
            );
        } finally {
            \fclose($resource);
        }
    }

    public function testAssertValueMapsNonStringMapKeyViolationToExistingContextReason(): void
    {
        self::assertContextWriteForbidden(
            operation: static fn (): mixed => new ContextStorePolicy()->assertValue(
                [
                    'safe' => [
                        1 => 'nested-integer-map-key',
                    ],
                ],
                ContextKeys::CORRELATION_ID,
            ),
            expectedPath: 'correlation_id.safe',
            expectedContextReason: 'context-write-forbidden-map-key',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'nested-integer-map-key',
            ],
        );
    }

    public function testAssertValueMapsUnsupportedTypeViolationToExistingContextReason(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        \fclose($resource);

        self::assertFalse(\is_resource($resource));
        self::assertSame('resource (closed)', \get_debug_type($resource));

        self::assertContextWriteForbidden(
            operation: static fn (): mixed => new ContextStorePolicy()->assertValue(
                [
                    'safe' => $resource,
                ],
                ContextKeys::CORRELATION_ID,
            ),
            expectedPath: 'correlation_id.safe',
            expectedContextReason: 'context-write-forbidden-type',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'resource (closed)',
                'php://memory',
            ],
        );
    }

    public function testAssertCanWritePreservesNestedPathFromContextKeyRoot(): void
    {
        self::assertContextWriteForbidden(
            operation: static fn (): mixed => new ContextStorePolicy()->assertCanWrite(
                ContextKeys::REQUEST_ID,
                [
                    'nested' => [
                        'value' => \INF,
                    ],
                ],
            ),
            expectedPath: 'request_id.nested.value',
            expectedContextReason: 'context-write-forbidden-float',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'INF',
            ],
        );
    }

    public function testAssertValueUsesSafePathPlaceholdersFromJsonLikeNormalizer(): void
    {
        self::assertContextWriteForbidden(
            operation: static fn (): mixed => new ContextStorePolicy()->assertValue(
                [
                    'safe' => [
                        'Authorization: Bearer raw-secret-token' => 1.25,
                    ],
                ],
                ContextKeys::CORRELATION_ID,
            ),
            expectedPath: 'correlation_id.safe[<key>]',
            expectedContextReason: 'context-write-forbidden-float',
            expectedJsonLikeReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'Authorization',
                'Bearer',
                'raw-secret-token',
            ],
        );
    }

    public function testAssertCanWritePreservesContextKeyPolicy(): void
    {
        $policy = new ContextStorePolicy();

        self::assertContextInvalidKey(
            operation: static fn (): mixed => $policy->assertCanWrite('', 'safe'),
            expectedMessage: 'context-key-empty',
            expectedErrorCode: ContextInvalidKeyException::ERROR_CODE,
            expectedReason: 'context-key-empty',
            expectedSafeKey: null,
        );

        self::assertContextInvalidKey(
            operation: static fn (): mixed => $policy->assertCanWrite('@reserved', 'safe'),
            expectedMessage: 'context-key-reserved: @reserved',
            expectedErrorCode: ContextInvalidKeyException::ERROR_CODE,
            expectedReason: 'context-key-reserved',
            expectedSafeKey: '@reserved',
        );

        self::assertContextInvalidKey(
            operation: static fn (): mixed => $policy->assertCanWrite('unknown_context_key', 'safe'),
            expectedMessage: 'context-key-unknown: unknown_context_key',
            expectedErrorCode: ContextInvalidKeyException::ERROR_CODE,
            expectedReason: 'context-key-unknown',
            expectedSafeKey: 'unknown_context_key',
        );
    }

    public function testAssertValueDoesNotNormalizeOrMutateStoredValuesAsSideEffect(): void
    {
        $value = [
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
            ],
        ];

        $original = $value;

        new ContextStorePolicy()->assertValue($value, ContextKeys::CORRELATION_ID);

        self::assertSame(
            $original,
            $value,
            'ContextStorePolicy must validate values only and must not normalize caller-owned arrays as a side effect.',
        );

        self::assertSame(
            [
                'zeta',
                'alpha',
                'items',
            ],
            \array_keys($value),
            'ContextStorePolicy must not reorder the top-level caller-owned map.',
        );

        self::assertSame(
            [
                'z',
                'a',
            ],
            \array_keys($value['alpha']),
            'ContextStorePolicy must not reorder nested caller-owned maps.',
        );

        self::assertSame(
            [
                'b',
                'a',
            ],
            \array_keys($value['items'][0]),
            'ContextStorePolicy must not reorder maps nested inside lists.',
        );
    }

    /**
     * @param callable(): mixed $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertContextWriteForbidden(
        callable $operation,
        string $expectedPath,
        string $expectedContextReason,
        string $expectedJsonLikeReason,
        array $forbiddenDiagnosticsNeedles = [],
    ): void {
        try {
            $operation();
            self::fail('Expected ContextWriteForbiddenException was not thrown.');
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame($expectedContextReason, $exception->reason());
            self::assertSame($expectedPath, $exception->safePath());
            self::assertSame(
                $expectedContextReason . ': value at ' . $expectedPath,
                $exception->getMessage(),
            );

            self::assertStringNotContainsString(
                $expectedJsonLikeReason,
                $exception->getMessage(),
                'ContextWriteForbiddenException diagnostics must expose only mapped context reason tokens.',
            );

            $previous = $exception->getPrevious();

            self::assertInstanceOf(JsonLikeNormalizationException::class, $previous);
            self::assertSame(JsonLikeNormalizationException::ERROR_CODE, $previous->errorCode());
            self::assertSame($expectedPath, $previous->path());
            self::assertSame($expectedJsonLikeReason, $previous->reason());
            self::assertStringContainsString($expectedJsonLikeReason, $previous->getMessage());

            foreach ($forbiddenDiagnosticsNeedles as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $exception->getMessage(),
                    'ContextStorePolicy diagnostics must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $exception->reason(),
                    'ContextWriteForbiddenException reasons must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $exception->safePath() ?? '',
                    'ContextWriteForbiddenException safe paths must not leak rejected raw values or unsafe details.',
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

    /**
     * @param callable(): mixed $operation
     */
    private static function assertContextInvalidKey(
        callable $operation,
        string $expectedMessage,
        string $expectedErrorCode,
        string $expectedReason,
        ?string $expectedSafeKey,
    ): void {
        try {
            $operation();
            self::fail('Expected ContextInvalidKeyException was not thrown.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSame($expectedErrorCode, $exception->errorCode());
            self::assertSame($expectedReason, $exception->reason());
            self::assertSame($expectedSafeKey, $exception->safeKey());
            self::assertSame($expectedMessage, $exception->getMessage());
        }
    }
}
