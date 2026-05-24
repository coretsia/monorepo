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

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException;
use Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkContext;
use Coretsia\Kernel\Runtime\UnitOfWorkResult;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;

final class KernelJsonLikePolicyMatchesFoundationContractTest extends TestCase
{
    public function testValidContextAttributesNormalizeToSameBaselineShapeAsFoundation(): void
    {
        $attributes = [
            'zeta' => true,
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
                false,
                42,
            ],
            'emptyList' => [],
        ];

        $context = self::makeContext($attributes);

        self::assertSame(
            JsonLikeNormalizer::normalize($attributes, 'attributes'),
            $context->attributes(),
            'Kernel context attributes must reuse Foundation baseline recursive normalization.',
        );
    }

    public function testValidResultExtensionsNormalizeToSameBaselineShapeAsFoundation(): void
    {
        $extensions = [
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
            'emptyList' => [],
        ];

        $result = self::makeResult($extensions);

        self::assertSame(
            JsonLikeNormalizer::normalize($extensions, 'extensions'),
            $result->extensions(),
            'Kernel result extensions must reuse Foundation baseline recursive normalization.',
        );
    }

    public function testKernelStillRejectsRootListsForAttributes(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext([
                'first',
                'second',
            ]),
            expectedPath: 'attributes',
            expectedReason: 'uow-context-attributes-root-map-required',
        );
    }

    public function testKernelStillRejectsRootListsForExtensions(): void
    {
        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'first',
                'second',
            ]),
            expectedPath: 'extensions',
            expectedReason: 'uow-result-extensions-root-map-required',
        );
    }

    public function testKernelStillRejectsUnsafeMetadataKeysForAttributesWithoutLeakingKeyOrValue(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext([
                'accessToken' => 'secret-token-value',
            ]),
            expectedPath: 'attributes[<key>]',
            expectedReason: 'uow-context-attributes-unsafe-key-forbidden',
            forbiddenDiagnosticsNeedles: [
                'accessToken',
                'secret-token-value',
            ],
        );
    }

    public function testKernelStillRejectsUnsafeMetadataKeysForExtensionsWithoutLeakingKeyOrValue(): void
    {
        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'accessToken' => 'secret-token-value',
            ]),
            expectedPath: 'extensions[<key>]',
            expectedReason: 'uow-result-extensions-unsafe-key-forbidden',
            forbiddenDiagnosticsNeedles: [
                'accessToken',
                'secret-token-value',
            ],
        );
    }

    public function testKernelStillAppliesAttributesMaxDepth(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext(
                attributes: [
                    'nested' => [
                        'value' => 'safe',
                    ],
                ],
                attributesMaxDepth: 1,
            ),
            expectedPath: 'attributes.nested',
            expectedReason: 'uow-context-attributes-max-depth-exceeded',
        );
    }

    public function testKernelStillAppliesAttributesMaxKeys(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext(
                attributes: [
                    'zeta' => 'last',
                    'alpha' => 'first',
                ],
                attributesMaxKeys: 1,
            ),
            expectedPath: 'attributes.zeta',
            expectedReason: 'uow-context-attributes-max-keys-exceeded',
        );
    }

    public function testFoundationFloatViolationsMapToContextAndResultReasonTokens(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext([
                'safe' => [
                    'value' => 12.34,
                ],
            ]),
            expectedPath: 'attributes.safe.value',
            expectedReason: 'uow-context-attributes-float-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                '12.34',
            ],
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'safe' => [
                    'value' => 12.34,
                ],
            ]),
            expectedPath: 'extensions.safe.value',
            expectedReason: 'uow-result-float-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                '12.34',
            ],
        );
    }

    public function testFoundationObjectViolationsMapToUowReasonTokensWithoutLeakingDetails(): void
    {
        $object = new class() {
            public string $secret = 'raw-object-secret-value';
        };

        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext([
                'safe' => $object,
            ]),
            expectedPath: 'attributes.safe',
            expectedReason: 'uow-context-attributes-object-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-object-secret-value',
                $object::class,
                'class@anonymous',
            ],
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'safe' => $object,
            ]),
            expectedPath: 'extensions.safe',
            expectedReason: 'uow-result-object-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-object-secret-value',
                $object::class,
                'class@anonymous',
            ],
        );
    }

    public function testFoundationClosureViolationsMapToUowReasonTokensWithoutLeakingDetails(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext([
                'safe' => static fn (): string => 'raw-closure-secret-value',
            ]),
            expectedPath: 'attributes.safe',
            expectedReason: 'uow-context-attributes-closure-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-closure-secret-value',
                'Closure',
            ],
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'safe' => static fn (): string => 'raw-closure-secret-value',
            ]),
            expectedPath: 'extensions.safe',
            expectedReason: 'uow-result-closure-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-closure-secret-value',
                'Closure',
            ],
        );
    }

    public function testFoundationResourceViolationsMapToUowReasonTokensWithoutLeakingDetails(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            self::assertContextInvalid(
                operation: static fn (): UnitOfWorkContext => self::makeContext([
                    'safe' => $resource,
                ]),
                expectedPath: 'attributes.safe',
                expectedReason: 'uow-context-attributes-resource-forbidden',
                expectedPreviousReason: JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN,
                forbiddenDiagnosticsNeedles: [
                    'php://memory',
                ],
            );

            self::assertResultInvalid(
                operation: static fn (): UnitOfWorkResult => self::makeResult([
                    'safe' => $resource,
                ]),
                expectedPath: 'extensions.safe',
                expectedReason: 'uow-result-resource-forbidden',
                expectedPreviousReason: JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN,
                forbiddenDiagnosticsNeedles: [
                    'php://memory',
                ],
            );
        } finally {
            \fclose($resource);
        }
    }

    public function testFoundationMapKeyViolationsMapToUowReasonTokensWithoutLeakingValues(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext([
                'safe' => [
                    1 => 'nested-integer-map-key',
                ],
            ]),
            expectedPath: 'attributes.safe',
            expectedReason: 'uow-context-attributes-map-key-must-be-string',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'nested-integer-map-key',
            ],
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'safe' => [
                    1 => 'nested-integer-map-key',
                ],
            ]),
            expectedPath: 'extensions.safe',
            expectedReason: 'uow-result-map-key-must-be-string',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING,
            forbiddenDiagnosticsNeedles: [
                'nested-integer-map-key',
            ],
        );
    }

    public function testFoundationTypeViolationsMapToUowReasonTokensWithoutLeakingDetails(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        \fclose($resource);

        self::assertFalse(\is_resource($resource));
        self::assertSame('resource (closed)', \get_debug_type($resource));

        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext([
                'safe' => $resource,
            ]),
            expectedPath: 'attributes.safe',
            expectedReason: 'uow-context-attributes-type-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'resource (closed)',
                'php://memory',
            ],
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'safe' => $resource,
            ]),
            expectedPath: 'extensions.safe',
            expectedReason: 'uow-result-type-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'resource (closed)',
                'php://memory',
            ],
        );
    }

    public function testKernelUowExceptionsDoNotLeakRawValuesFromFoundationLevelFailures(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::makeContext([
                'safe' => [
                    'nested' => [
                        'value' => 9876.54321,
                    ],
                ],
            ]),
            expectedPath: 'attributes.safe.nested.value',
            expectedReason: 'uow-context-attributes-float-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                '9876.54321',
                'payload',
                'secret',
                'token',
                'cookie',
                'Authorization',
                'stacktrace',
                'raw SQL',
            ],
        );

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'safe' => [
                    'nested' => [
                        'value' => 9876.54321,
                    ],
                ],
            ]),
            expectedPath: 'extensions.safe.nested.value',
            expectedReason: 'uow-result-float-forbidden',
            expectedPreviousReason: JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                '9876.54321',
                'payload',
                'secret',
                'token',
                'cookie',
                'Authorization',
                'stacktrace',
                'raw SQL',
            ],
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @param int<1, max> $attributesMaxDepth
     * @param int<1, max> $attributesMaxKeys
     */
    private static function makeContext(
        array $attributes = [],
        int $attributesMaxDepth = 10,
        int $attributesMaxKeys = 200,
    ): UnitOfWorkContext {
        return new UnitOfWorkContext(
            uowId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            type: UnitOfWorkType::HTTP,
            startedAt: 1_710_000_000_123,
            correlationId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
            attributes: $attributes,
            attributesMaxDepth: $attributesMaxDepth,
            attributesMaxKeys: $attributesMaxKeys,
        );
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private static function makeResult(array $extensions = []): UnitOfWorkResult
    {
        return new UnitOfWorkResult(
            uowId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N0',
            type: UnitOfWorkType::HTTP,
            correlationId: '01HV7N3ZJ5P8K7Y6T4R3Q2P1N1',
            startedAt: 1_710_000_000_123,
            finishedAt: 1_710_000_000_456,
            durationMs: 333,
            outcome: Outcome::SUCCESS,
            extensions: $extensions,
        );
    }

    /**
     * @param callable(): mixed $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertContextInvalid(
        callable $operation,
        string $expectedPath,
        string $expectedReason,
        ?string $expectedPreviousReason = null,
        array $forbiddenDiagnosticsNeedles = [],
    ): void {
        try {
            $operation();
            self::fail('Expected UnitOfWorkContextInvalidException was not thrown.');
        } catch (UnitOfWorkContextInvalidException $exception) {
            self::assertSame(UnitOfWorkContextInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame('CORETSIA_UOW_CONTEXT_INVALID', $exception->errorCode());
            self::assertSame($expectedPath, $exception->path());
            self::assertSame($expectedReason, $exception->reason());
            self::assertStringContainsString($expectedReason, $exception->getMessage());
            self::assertStringContainsString($expectedPath, $exception->getMessage());

            if ($expectedPreviousReason !== null) {
                $previous = $exception->getPrevious();

                self::assertInstanceOf(JsonLikeNormalizationException::class, $previous);
                self::assertSame(JsonLikeNormalizationException::ERROR_CODE, $previous->errorCode());
                self::assertSame($expectedPath, $previous->path());
                self::assertSame($expectedPreviousReason, $previous->reason());
            }

            self::assertNoDiagnosticLeak(
                exception: $exception,
                forbiddenDiagnosticsNeedles: $forbiddenDiagnosticsNeedles,
            );
        }
    }

    /**
     * @param callable(): mixed $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertResultInvalid(
        callable $operation,
        string $expectedPath,
        string $expectedReason,
        ?string $expectedPreviousReason = null,
        array $forbiddenDiagnosticsNeedles = [],
    ): void {
        try {
            $operation();
            self::fail('Expected UnitOfWorkResultInvalidException was not thrown.');
        } catch (UnitOfWorkResultInvalidException $exception) {
            self::assertSame(UnitOfWorkResultInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame('CORETSIA_UOW_RESULT_INVALID', $exception->errorCode());
            self::assertSame($expectedPath, $exception->path());
            self::assertSame($expectedReason, $exception->reason());
            self::assertStringContainsString($expectedReason, $exception->getMessage());
            self::assertStringContainsString($expectedPath, $exception->getMessage());

            if ($expectedPreviousReason !== null) {
                $previous = $exception->getPrevious();

                self::assertInstanceOf(JsonLikeNormalizationException::class, $previous);
                self::assertSame(JsonLikeNormalizationException::ERROR_CODE, $previous->errorCode());
                self::assertSame($expectedPath, $previous->path());
                self::assertSame($expectedPreviousReason, $previous->reason());
            }

            self::assertNoDiagnosticLeak(
                exception: $exception,
                forbiddenDiagnosticsNeedles: $forbiddenDiagnosticsNeedles,
            );
        }
    }

    /**
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertNoDiagnosticLeak(
        UnitOfWorkContextInvalidException|UnitOfWorkResultInvalidException $exception,
        array $forbiddenDiagnosticsNeedles,
    ): void {
        foreach ($forbiddenDiagnosticsNeedles as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $exception->getMessage(),
                'Kernel UoW diagnostics must not leak rejected raw values or unsafe details.',
            );

            self::assertStringNotContainsString(
                $needle,
                $exception->path(),
                'Kernel UoW paths must not leak rejected raw values or unsafe details.',
            );

            self::assertStringNotContainsString(
                $needle,
                $exception->reason(),
                'Kernel UoW reasons must not leak rejected raw values or unsafe details.',
            );

            $previous = $exception->getPrevious();

            if ($previous instanceof JsonLikeNormalizationException) {
                self::assertStringNotContainsString(
                    $needle,
                    $previous->getMessage(),
                    'Foundation json-like diagnostics must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $previous->path(),
                    'Foundation json-like paths must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $previous->reason(),
                    'Foundation json-like reasons must not leak rejected raw values or unsafe details.',
                );
            }
        }
    }
}
