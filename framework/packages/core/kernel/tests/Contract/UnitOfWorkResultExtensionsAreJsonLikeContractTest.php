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

use Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkResult;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnitOfWorkResultExtensionsAreJsonLikeContractTest extends TestCase
{
    public function testExtensionsAcceptJsonLikeValuesAndNormalizeMapsRecursively(): void
    {
        $result = self::makeResult([
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
        ]);

        self::assertSame(
            [
                'alpha' => [
                    'a' => 'nested-first',
                    'z' => 'nested-last',
                ],
                'emptyList' => [],
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
            $result->extensions(),
        );

        self::assertSame(
            [
                'alpha',
                'emptyList',
                'items',
                'zeta',
            ],
            \array_keys($result->extensions()),
            'Extension maps must be recursively sorted by byte-order key comparison.',
        );
    }

    public function testExtensionsRootMustBeMapNotList(): void
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

    public function testExtensionsRejectIntegerMapKeys(): void
    {
        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'nested' => [
                    1 => 'one',
                ],
            ]),
            expectedPath: 'extensions.nested',
            expectedReason: 'uow-result-map-key-must-be-string',
        );
    }

    #[DataProvider('forbiddenScalarProvider')]
    public function testExtensionsRejectForbiddenScalars(
        mixed $value,
        string $expectedPath,
        string $expectedReason,
        string $rawValueNeedle,
    ): void {
        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'safe' => [
                    'nested' => [
                        'value' => $value,
                    ],
                ],
            ]),
            expectedPath: $expectedPath,
            expectedReason: $expectedReason,
            forbiddenDiagnosticsNeedles: [$rawValueNeedle],
        );
    }

    /**
     * @return iterable<string, array{value: mixed, expectedPath: string, expectedReason: string, rawValueNeedle: string}>
     */
    public static function forbiddenScalarProvider(): iterable
    {
        yield 'float' => [
            'value' => 12.34,
            'expectedPath' => 'extensions.safe.nested.value',
            'expectedReason' => 'uow-result-float-forbidden',
            'rawValueNeedle' => '12.34',
        ];

        yield 'NaN' => [
            'value' => \NAN,
            'expectedPath' => 'extensions.safe.nested.value',
            'expectedReason' => 'uow-result-float-forbidden',
            'rawValueNeedle' => 'NAN',
        ];

        yield 'INF' => [
            'value' => \INF,
            'expectedPath' => 'extensions.safe.nested.value',
            'expectedReason' => 'uow-result-float-forbidden',
            'rawValueNeedle' => 'INF',
        ];

        yield '-INF' => [
            'value' => -\INF,
            'expectedPath' => 'extensions.safe.nested.value',
            'expectedReason' => 'uow-result-float-forbidden',
            'rawValueNeedle' => '-INF',
        ];
    }

    public function testExtensionsRejectObjectsWithoutLeakingObjectDetails(): void
    {
        $object = new class() {
            public string $secret = 'raw-object-secret-value';
        };

        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'object' => $object,
            ]),
            expectedPath: 'extensions.object',
            expectedReason: 'uow-result-object-forbidden',
            forbiddenDiagnosticsNeedles: [
                'raw-object-secret-value',
                $object::class,
                'class@anonymous',
            ],
        );
    }

    public function testExtensionsRejectClosuresWithoutLeakingObjectDetails(): void
    {
        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'callback' => static fn (): string => 'raw-closure-secret-value',
            ]),
            expectedPath: 'extensions.callback',
            expectedReason: 'uow-result-closure-forbidden',
            forbiddenDiagnosticsNeedles: [
                'raw-closure-secret-value',
                'Closure',
            ],
        );
    }

    public function testExtensionsRejectResourcesWithoutLeakingResourceDetails(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            self::assertResultInvalid(
                operation: static fn (): UnitOfWorkResult => self::makeResult([
                    'stream' => $resource,
                ]),
                expectedPath: 'extensions.stream',
                expectedReason: 'uow-result-resource-forbidden',
                forbiddenDiagnosticsNeedles: [
                    'php://memory',
                ],
            );
        } finally {
            \fclose($resource);
        }
    }

    #[DataProvider('unsafeExtensionKeyProvider')]
    public function testExtensionsRejectUnsafeSemanticKeys(string $key): void
    {
        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                $key => 'redacted-value-that-must-not-leak',
            ]),
            expectedPath: 'extensions[<key>]',
            expectedReason: 'uow-result-extensions-unsafe-key-forbidden',
            forbiddenDiagnosticsNeedles: [
                $key,
                'redacted-value-that-must-not-leak',
            ],
        );
    }

    /**
     * @return iterable<string, array{key: string}>
     */
    public static function unsafeExtensionKeyProvider(): iterable
    {
        foreach (
            [
                'authorization',
                'cookie',
                'cookies',
                'session',
                'sessionId',
                'session_id',
                'token',
                'tokens',
                'accessToken',
                'access_token',
                'refreshToken',
                'refresh_token',
                'password',
                'secret',
                'credential',
                'credentials',
                'raw',
                'rawBody',
                'rawPayload',
                'payload',
                'rawSql',
                'sql',
                'stacktrace',
                'stackTrace',
                'trace',
                'email',
                'phone',
                'username',
                'fullName',
                'userId',
                'tenantId',
            ] as $key
        ) {
            yield $key => [
                'key' => $key,
            ];
        }
    }

    public function testExtensionsRejectUnsafeSemanticKeysRecursively(): void
    {
        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'safe' => [
                    'nested' => [
                        'accessToken' => 'secret-token-value',
                    ],
                ],
            ]),
            expectedPath: 'extensions.safe.nested[<key>]',
            expectedReason: 'uow-result-extensions-unsafe-key-forbidden',
            forbiddenDiagnosticsNeedles: [
                'accessToken',
                'secret-token-value',
            ],
        );
    }

    public function testExtensionStringValuesRejectUnsafeControlCharactersWithoutLeakingRawValue(): void
    {
        self::assertResultInvalid(
            operation: static fn (): UnitOfWorkResult => self::makeResult([
                'safe' => "safe-prefix\0raw-secret-suffix",
            ]),
            expectedPath: 'extensions.safe',
            expectedReason: 'uow-result-string-invalid',
            forbiddenDiagnosticsNeedles: [
                'safe-prefix',
                'raw-secret-suffix',
            ],
        );
    }

    public function testDiagnosticsContainOnlyErrorCodeReasonAndPath(): void
    {
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
    private static function assertResultInvalid(
        callable $operation,
        string $expectedPath,
        string $expectedReason,
        array $forbiddenDiagnosticsNeedles = [],
    ): void {
        try {
            $operation();
            self::fail('Expected UnitOfWorkResultInvalidException was not thrown.');
        } catch (UnitOfWorkResultInvalidException $exception) {
            self::assertSame(
                UnitOfWorkResultInvalidException::ERROR_CODE,
                $exception->errorCode(),
            );

            self::assertSame(
                'CORETSIA_UOW_RESULT_INVALID',
                $exception->errorCode(),
            );

            self::assertSame($expectedPath, $exception->path());
            self::assertSame($expectedReason, $exception->reason());

            self::assertStringContainsString($expectedReason, $exception->getMessage());
            self::assertStringContainsString($expectedPath, $exception->getMessage());

            foreach ($forbiddenDiagnosticsNeedles as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $exception->getMessage(),
                    'UnitOfWorkResult diagnostics must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $exception->path(),
                    'UnitOfWorkResult paths must not leak rejected raw values or unsafe details.',
                );

                self::assertStringNotContainsString(
                    $needle,
                    $exception->reason(),
                    'UnitOfWorkResult reasons must not leak rejected raw values or unsafe details.',
                );
            }
        }
    }
}
