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
use Coretsia\Foundation\Serialization\JsonLikeNormalizationLimits;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use PHPUnit\Framework\TestCase;

final class JsonLikeNormalizationLimitsContractTest extends TestCase
{
    public function testOmittedLimitsPreserveBaselineCompatibility(): void
    {
        $deepValue = self::nestedList(12);
        $wideValue = \array_fill(0, 300, 'value');
        $longString = \str_repeat('x', 5000);
        $longMapKey = \str_repeat('k', 5000);

        self::assertSame(
            $deepValue,
            JsonLikeNormalizer::normalize($deepValue),
        );

        self::assertSame(
            $wideValue,
            JsonLikeNormalizer::normalize($wideValue),
        );

        self::assertSame(
            $longString,
            JsonLikeNormalizer::normalize($longString),
        );

        self::assertSame(
            [
                $longMapKey => 'value',
            ],
            JsonLikeNormalizer::normalize([
                $longMapKey => 'value',
            ]),
        );
    }

    public function testLimitValuesMustBePositive(): void
    {
        foreach (
            [
                [
                    0,
                    1,
                    1,
                    'json-like-normalization-max-depth-invalid',
                ],
                [
                    -1,
                    1,
                    1,
                    'json-like-normalization-max-depth-invalid',
                ],
                [
                    1,
                    0,
                    1,
                    'json-like-normalization-max-nodes-invalid',
                ],
                [
                    1,
                    -1,
                    1,
                    'json-like-normalization-max-nodes-invalid',
                ],
                [
                    1,
                    1,
                    0,
                    'json-like-normalization-max-string-bytes-invalid',
                ],
                [
                    1,
                    1,
                    -1,
                    'json-like-normalization-max-string-bytes-invalid',
                ],
            ] as [$maxDepth,
                $maxNodes,
                $maxStringBytes,
                $expectedMessage,]
        ) {
            try {
                new JsonLikeNormalizationLimits(
                    maxDepth: $maxDepth,
                    maxNodes: $maxNodes,
                    maxStringBytes: $maxStringBytes,
                );

                self::fail(
                    'Expected invalid normalization limit rejection.',
                );
            } catch (\InvalidArgumentException $exception) {
                self::assertSame(
                    $expectedMessage,
                    $exception->getMessage(),
                );
            }
        }
    }

    public function testLimitsValueObjectHasStableImmutableShape(): void
    {
        $class = new \ReflectionClass(
            JsonLikeNormalizationLimits::class,
        );

        self::assertTrue($class->isFinal());
        self::assertTrue($class->isReadOnly());

        self::assertSame(
            [
                'maxDepth',
                'maxNodes',
                'maxStringBytes',
            ],
            \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                $class->getProperties(\ReflectionProperty::IS_PUBLIC),
            ),
        );

        $limits = new JsonLikeNormalizationLimits(
            maxDepth: 8,
            maxNodes: 256,
            maxStringBytes: 4096,
        );

        self::assertSame(8, $limits->maxDepth);
        self::assertSame(256, $limits->maxNodes);
        self::assertSame(4096, $limits->maxStringBytes);
    }

    public function testNormalizerExposesOptionalLimitsParameter(): void
    {
        $method = new \ReflectionMethod(
            JsonLikeNormalizer::class,
            'normalize',
        );

        $parameters = $method->getParameters();

        self::assertCount(3, $parameters);

        self::assertSame('value', $parameters[0]->getName());
        self::assertSame('path', $parameters[1]->getName());
        self::assertSame('limits', $parameters[2]->getName());

        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertSame('value', $parameters[1]->getDefaultValue());

        self::assertTrue($parameters[2]->isDefaultValueAvailable());
        self::assertNull($parameters[2]->getDefaultValue());

        $type = $parameters[2]->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(
            JsonLikeNormalizationLimits::class,
            $type->getName(),
        );
        self::assertTrue($type->allowsNull());
    }

    public function testRootContainerDepthIsOneAndMapAndListDepthMatch(): void
    {
        $limits = new JsonLikeNormalizationLimits(
            maxDepth: 2,
            maxNodes: 20,
            maxStringBytes: 100,
        );

        self::assertSame(
            [['leaf']],
            JsonLikeNormalizer::normalize(
                [['leaf']],
                limits: $limits,
            ),
        );

        self::assertSame(
            ['level' => ['leaf']],
            JsonLikeNormalizer::normalize(
                ['level' => ['leaf']],
                limits: $limits,
            ),
        );

        self::assertReason(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize(
                [[['leaf']]],
                limits: $limits,
            ),
            expectedReason: JsonLikeNormalizationException::REASON_MAX_DEPTH_EXCEEDED,
            expectedPath: 'value[0][0]',
        );

        self::assertReason(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize(
                [
                    'level' => [
                        'level' => [
                            'leaf',
                        ],
                    ],
                ],
                limits: $limits,
            ),
            expectedReason: JsonLikeNormalizationException::REASON_MAX_DEPTH_EXCEEDED,
            expectedPath: 'value.level.level',
        );
    }

    public function testListItemsMapValuesAndNestedScalarsConsumeNodeBudget(): void
    {
        $limits = new JsonLikeNormalizationLimits(
            maxDepth: 8,
            maxNodes: 2,
            maxStringBytes: 100,
        );

        self::assertSame(
            ['a', 'b'],
            JsonLikeNormalizer::normalize(
                ['a', 'b'],
                limits: $limits,
            ),
        );

        self::assertSame(
            [
                'a' => 1,
                'b' => 2,
            ],
            JsonLikeNormalizer::normalize(
                [
                    'b' => 2,
                    'a' => 1,
                ],
                limits: $limits,
            ),
        );

        self::assertReason(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize(
                [
                    'a',
                    ['b'],
                ],
                limits: $limits,
            ),
            expectedReason: JsonLikeNormalizationException::REASON_MAX_NODES_EXCEEDED,
            expectedPath: 'value[1][0]',
        );
    }

    public function testMapNodeBudgetFailureDoesNotDependOnInsertionOrder(): void
    {
        $ascending = [];

        for ($i = 0; $i < 257; $i++) {
            $ascending[\sprintf('node_%03d', $i)] = 'value';
        }

        $descending = \array_reverse(
            $ascending,
            preserve_keys: true,
        );

        $limits = new JsonLikeNormalizationLimits(
            maxDepth: 8,
            maxNodes: 256,
            maxStringBytes: 4096,
        );

        $first = self::captureNormalizationFailure(
            static fn (): mixed => JsonLikeNormalizer::normalize(
                $ascending,
                limits: $limits,
            ),
        );

        $second = self::captureNormalizationFailure(
            static fn (): mixed => JsonLikeNormalizer::normalize(
                $descending,
                limits: $limits,
            ),
        );

        self::assertSame(
            JsonLikeNormalizationException::REASON_MAX_NODES_EXCEEDED,
            $first->reason(),
        );

        self::assertSame('value', $first->path());
        self::assertSame($first->reason(), $second->reason());
        self::assertSame($first->path(), $second->path());
    }

    public function testStringByteLimitAppliesToValuesAndMapKeys(): void
    {
        $limits = new JsonLikeNormalizationLimits(
            maxDepth: 8,
            maxNodes: 20,
            maxStringBytes: 4,
        );

        self::assertSame(
            '1234',
            JsonLikeNormalizer::normalize(
                '1234',
                limits: $limits,
            ),
        );

        self::assertSame(
            [
                'abcd' => 'ok',
            ],
            JsonLikeNormalizer::normalize(
                [
                    'abcd' => 'ok',
                ],
                limits: $limits,
            ),
        );

        self::assertReason(
            operation: static fn (): mixed =>
            JsonLikeNormalizer::normalize(
                '12345',
                limits: $limits,
            ),
            expectedReason: JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
            expectedPath: 'value',
        );

        self::assertReason(
            operation: static fn (): mixed =>
            JsonLikeNormalizer::normalize(
                [
                    'abcde' => 'ok',
                ],
                limits: $limits,
            ),
            expectedReason: JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
            expectedPath: 'value.abcde',
        );
    }

    public function testNodeLimitIsCheckedBeforeDescendingIntoRejectedValue(): void
    {
        $limits = new JsonLikeNormalizationLimits(
            maxDepth: 8,
            maxNodes: 1,
            maxStringBytes: 100,
        );

        self::assertReason(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize(
                [
                    'safe',
                    new \stdClass(),
                ],
                limits: $limits,
            ),
            expectedReason: JsonLikeNormalizationException::REASON_MAX_NODES_EXCEEDED,
            expectedPath: 'value[1]',
        );
    }

    public function testLimitedNormalizationDoesNotMutateCallerArrays(): void
    {
        $value = [
            'zeta' => [
                'b' => 2,
                'a' => 1,
            ],
            'alpha' => 'first',
        ];

        $original = $value;

        JsonLikeNormalizer::normalize(
            $value,
            limits: new JsonLikeNormalizationLimits(
                maxDepth: 8,
                maxNodes: 20,
                maxStringBytes: 100,
            ),
        );

        self::assertSame($original, $value);
        self::assertSame(
            [
                'zeta',
                'alpha',
            ],
            \array_keys($value),
        );
        self::assertSame(
            [
                'b',
                'a',
            ],
            \array_keys($value['zeta']),
        );
    }

    public function testLimitDiagnosticsDoNotExposeUnsafeData(): void
    {
        $unsafeKey = 'Authorization Bearer raw-secret-token';

        try {
            JsonLikeNormalizer::normalize(
                [
                    $unsafeKey => 'raw-string-sentinel',
                ],
                limits: new JsonLikeNormalizationLimits(
                    maxDepth: 8,
                    maxNodes: 20,
                    maxStringBytes: 4,
                ),
            );

            self::fail(
                'Expected safe limited-normalization failure.',
            );
        } catch (JsonLikeNormalizationException $exception) {
            self::assertSame(
                JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
                $exception->reason(),
            );
            self::assertSame(
                'value[<key>]',
                $exception->path(),
            );
            self::assertStringNotContainsString(
                $unsafeKey,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                'raw-string-sentinel',
                $exception->getMessage(),
            );
        }
    }

    public function testStringLimitUsesBytesRatherThanCharacterCount(): void
    {
        $threeByteCharacter = '€';

        self::assertSame(
            $threeByteCharacter,
            JsonLikeNormalizer::normalize(
                $threeByteCharacter,
                limits: new JsonLikeNormalizationLimits(
                    maxDepth: 8,
                    maxNodes: 20,
                    maxStringBytes: 3,
                ),
            ),
        );

        self::assertReason(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize(
                $threeByteCharacter,
                limits: new JsonLikeNormalizationLimits(
                    maxDepth: 8,
                    maxNodes: 20,
                    maxStringBytes: 2,
                ),
            ),
            expectedReason: JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
            expectedPath: 'value',
        );
    }

    private static function captureNormalizationFailure(
        callable $operation,
    ): JsonLikeNormalizationException {
        try {
            $operation();

            self::fail(
                'Expected JsonLikeNormalizationException.',
            );
        } catch (JsonLikeNormalizationException $exception) {
            return $exception;
        }
    }

    private static function assertReason(
        callable $operation,
        string $expectedReason,
        string $expectedPath,
    ): void {
        try {
            $operation();

            self::fail(
                'Expected json-like normalization limit failure.',
            );
        } catch (JsonLikeNormalizationException $exception) {
            self::assertSame(
                $expectedReason,
                $exception->reason(),
            );
            self::assertSame(
                $expectedPath,
                $exception->path(),
            );
        }
    }

    private static function nestedList(int $depth): array
    {
        $value = 'leaf';

        for ($i = 0; $i < $depth; $i++) {
            $value = [$value];
        }

        return $value;
    }
}
