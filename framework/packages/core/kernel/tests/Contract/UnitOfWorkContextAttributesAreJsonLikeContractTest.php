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

use Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException;
use Coretsia\Kernel\Runtime\UnitOfWorkContext;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;

final class UnitOfWorkContextAttributesAreJsonLikeContractTest extends TestCase
{
    public function testContextAttributesAcceptJsonLikeValuesAndNormalizeMapsRecursively(): void
    {
        $context = self::contextWithAttributes([
            'zeta' => true,
            'alpha' => [
                'delta' => 4,
                'beta' => null,
            ],
            'list' => [
                [
                    'b' => 2,
                    'a' => 1,
                ],
                'kept-in-list-order',
            ],
            'count' => 3,
            'label' => 'safe-label',
            'enabled' => false,
        ]);

        self::assertSame(
            [
                'alpha' => [
                    'beta' => null,
                    'delta' => 4,
                ],
                'count' => 3,
                'enabled' => false,
                'label' => 'safe-label',
                'list' => [
                    [
                        'a' => 1,
                        'b' => 2,
                    ],
                    'kept-in-list-order',
                ],
                'zeta' => true,
            ],
            $context->attributes(),
            'Context attributes must be json-like and recursively normalized by strcmp map ordering while preserving list order.',
        );

        self::assertSame(
            $context->attributes(),
            $context->toArray()['attributes'],
            'Exported context shape must carry the normalized attributes map.',
        );
    }

    public function testContextAttributesRejectRootList(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                'first',
                'second',
            ]),
            expectedPath: 'attributes',
            expectedReason: 'uow-context-attributes-root-map-required',
        );
    }

    public function testContextAttributesRejectIntegerMapKeys(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                1 => 'not-a-list-because-index-zero-is-missing',
            ]),
            expectedPath: 'attributes',
            expectedReason: 'uow-context-attributes-map-key-must-be-string',
        );

        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                'safe' => [
                    1 => 'nested-map-with-int-key',
                ],
            ]),
            expectedPath: 'attributes.safe',
            expectedReason: 'uow-context-attributes-map-key-must-be-string',
        );
    }

    public function testContextAttributesRejectFloatsNaNAndInfinity(): void
    {
        $cases = [
            'finite float' => 1.5,
            'NaN' => \NAN,
            'INF' => \INF,
            '-INF' => -\INF,
        ];

        foreach ($cases as $caseName => $value) {
            self::assertContextInvalid(
                operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                    'safe' => $value,
                ]),
                expectedPath: 'attributes.safe',
                expectedReason: 'uow-context-attributes-float-forbidden',
                caseName: $caseName,
            );
        }
    }

    public function testContextAttributesRejectObjectsClosuresAndResources(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                'safe' => new \stdClass(),
            ]),
            expectedPath: 'attributes.safe',
            expectedReason: 'uow-context-attributes-object-forbidden',
            caseName: 'object',
        );

        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                'safe' => static fn (): string => 'not-json-like',
            ]),
            expectedPath: 'attributes.safe',
            expectedReason: 'uow-context-attributes-closure-forbidden',
            caseName: 'closure',
        );

        $resource = \fopen('php://memory', 'rb');

        if ($resource === false) {
            self::fail('Unable to open php://memory test resource.');
        }

        try {
            self::assertContextInvalid(
                operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                    'safe' => $resource,
                ]),
                expectedPath: 'attributes.safe',
                expectedReason: 'uow-context-attributes-resource-forbidden',
                caseName: 'resource',
            );
        } finally {
            \fclose($resource);
        }
    }

    public function testContextAttributesRejectUnsafeKeysDeterministically(): void
    {
        $unsafeKeys = [
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
        ];

        foreach ($unsafeKeys as $unsafeKey) {
            self::assertContextInvalid(
                operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                    $unsafeKey => 'secret-value-must-not-leak',
                ]),
                expectedPath: 'attributes[<key>]',
                expectedReason: 'uow-context-attributes-unsafe-key-forbidden',
                leakedRawValues: [
                    $unsafeKey,
                    'secret-value-must-not-leak',
                ],
                caseName: $unsafeKey,
            );
        }
    }

    public function testContextAttributesRejectNestedUnsafeKeysDeterministically(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                'adapter' => [
                    'metadata' => [
                        'accessToken' => 'token-value-must-not-leak',
                    ],
                ],
            ]),
            expectedPath: 'attributes.adapter.metadata[<key>]',
            expectedReason: 'uow-context-attributes-unsafe-key-forbidden',
            leakedRawValues: [
                'accessToken',
                'token-value-must-not-leak',
            ],
        );
    }

    public function testContextAttributesRejectOverDepthPayloadsDeterministically(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes(
                attributes: [
                    'outer' => [
                        'inner' => true,
                    ],
                ],
                attributesMaxDepth: 1,
            ),
            expectedPath: 'attributes.outer',
            expectedReason: 'uow-context-attributes-max-depth-exceeded',
        );

        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes(
                attributes: [
                    'list' => [
                        [
                            'inside' => true,
                        ],
                    ],
                ],
                attributesMaxDepth: 1,
            ),
            expectedPath: 'attributes.list',
            expectedReason: 'uow-context-attributes-max-depth-exceeded',
        );
    }

    public function testContextAttributesRejectOverKeyLimitPayloadsDeterministically(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes(
                attributes: [
                    'first' => 1,
                    'second' => 2,
                ],
                attributesMaxKeys: 1,
            ),
            expectedPath: 'attributes.second',
            expectedReason: 'uow-context-attributes-max-keys-exceeded',
        );

        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes(
                attributes: [
                    'first' => [
                        'nested' => 1,
                    ],
                ],
                attributesMaxKeys: 1,
            ),
            expectedPath: 'attributes.first.nested',
            expectedReason: 'uow-context-attributes-max-keys-exceeded',
        );
    }

    public function testContextAttributesRejectUnsafeStringsWithoutLeakingRawValues(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                'safe' => "prefix-secret-value\0suffix-token-value",
            ]),
            expectedPath: 'attributes.safe',
            expectedReason: 'uow-context-attributes-string-invalid',
            leakedRawValues: [
                'prefix-secret-value',
                'suffix-token-value',
                'secret-value',
                'token-value',
            ],
        );
    }

    public function testContextAttributeFailureDiagnosticsDoNotLeakRawPayloadsSecretsSqlOrLocalPaths(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                'rawSql' => 'SELECT * FROM users WHERE password = "secret-value"',
            ]),
            expectedPath: 'attributes[<key>]',
            expectedReason: 'uow-context-attributes-unsafe-key-forbidden',
            leakedRawValues: [
                'rawSql',
                'SELECT * FROM users',
                'password = "secret-value"',
                'secret-value',
                __DIR__,
                \dirname(__DIR__, 2),
            ],
        );
    }

    public function testContextAttributesRejectInvalidMapKeysWithSafeDiagnostics(): void
    {
        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                "bad\nkey" => 'value-must-not-leak',
            ]),
            expectedPath: 'attributes',
            expectedReason: 'uow-context-attributes-map-key-invalid',
            leakedRawValues: [
                'value-must-not-leak',
            ],
        );

        self::assertContextInvalid(
            operation: static fn (): UnitOfWorkContext => self::contextWithAttributes([
                'safe' => [
                    "bad\rkey" => 'nested-value-must-not-leak',
                ],
            ]),
            expectedPath: 'attributes.safe',
            expectedReason: 'uow-context-attributes-map-key-invalid',
            leakedRawValues: [
                'nested-value-must-not-leak',
            ],
        );
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    private static function contextWithAttributes(
        array $attributes,
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
     * @param callable(): mixed $operation
     * @param list<string> $leakedRawValues
     */
    private static function assertContextInvalid(
        callable $operation,
        string $expectedPath,
        string $expectedReason,
        array $leakedRawValues = [],
        string $caseName = '',
    ): void {
        try {
            $operation();
            self::fail(
                ($caseName === '' ? '' : $caseName . ': ')
                . 'Expected UnitOfWorkContextInvalidException was not thrown.',
            );
        } catch (UnitOfWorkContextInvalidException $exception) {
            self::assertSame(
                UnitOfWorkContextInvalidException::ERROR_CODE,
                $exception->errorCode(),
                $caseName,
            );

            self::assertSame(
                'CORETSIA_UOW_CONTEXT_INVALID',
                $exception->errorCode(),
                $caseName,
            );

            self::assertSame($expectedPath, $exception->path(), $caseName);
            self::assertSame($expectedReason, $exception->reason(), $caseName);

            self::assertStringContainsString($expectedReason, $exception->getMessage(), $caseName);
            self::assertStringContainsString($expectedPath, $exception->getMessage(), $caseName);

            foreach ($leakedRawValues as $leakedRawValue) {
                self::assertStringNotContainsString(
                    $leakedRawValue,
                    $exception->getMessage(),
                    $caseName,
                );

                self::assertStringNotContainsString(
                    $leakedRawValue,
                    $exception->path(),
                    $caseName,
                );

                self::assertStringNotContainsString(
                    $leakedRawValue,
                    $exception->reason(),
                    $caseName,
                );
            }
        }
    }
}
