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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CorrelationIdProviderRejectsUnsafeCorrelationIdsTest extends TestCase
{
    private const string CANONICAL_CORRELATION_ID_PATTERN = '/\A[0-9A-HJKMNP-TV-Z]{26}\z/';

    public function testProviderReturnsCanonicalUlidCorrelationId(): void
    {
        $store = new ContextStore();
        $provider = new CorrelationIdProvider($store);

        $store->set(ContextKeys::CORRELATION_ID, '01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $correlationId = $provider->correlationId();

        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $correlationId);
        self::assertMatchesRegularExpression(self::CANONICAL_CORRELATION_ID_PATTERN, $correlationId);

        self::assertTrue($store->has(ContextKeys::CORRELATION_ID));
        self::assertSame(
            [
                ContextKeys::CORRELATION_ID => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ],
            $store->all(),
        );
    }

    public function testProviderDoesNotGenerateCorrelationIdWhenAbsent(): void
    {
        $store = new ContextStore();
        $provider = new CorrelationIdProvider($store);

        self::assertNull($provider->correlationId());
        self::assertFalse($store->has(ContextKeys::CORRELATION_ID));
        self::assertSame([], $store->all());
    }

    /**
     * @return iterable<string,array{0:mixed}>
     */
    public static function unsafeCorrelationIdProvider(): iterable
    {
        yield 'empty string' => [''];

        yield 'null value' => [null];

        yield 'integer value' => [123];

        yield 'boolean value' => [true];

        yield 'list value' => [
            [
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ],
        ];

        yield 'map value' => [
            [
                'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ],
        ];

        yield 'lowercase ulid-like value' => ['01arz3ndektsv4rrffq69g5fav'];

        yield 'mixed-case ulid-like value' => ['01ARZ3NDEKTSV4rrFFQ69G5FAV'];

        yield 'too short ulid-like value' => ['01ARZ3NDEKTSV4RRFFQ69G5FA'];

        yield 'too long ulid-like value' => ['01ARZ3NDEKTSV4RRFFQ69G5FAVX'];

        yield 'ulid-like value with forbidden I' => ['01ARZ3NDEKTSV4RRFFQ69G5FAI'];

        yield 'ulid-like value with forbidden L' => ['01ARZ3NDEKTSV4RRFFQ69G5FAL'];

        yield 'ulid-like value with forbidden O' => ['01ARZ3NDEKTSV4RRFFQ69G5FAO'];

        yield 'ulid-like value with forbidden U' => ['01ARZ3NDEKTSV4RRFFQ69G5FAU'];

        yield 'token-like string' => ['Bearer raw-secret-token'];

        yield 'authorization-like string' => ['Authorization: Bearer raw-secret-token'];

        yield 'cookie-like string' => ['Cookie: session_id=raw-secret-cookie'];

        yield 'session-like string' => ['session_id=raw-secret-session'];

        yield 'credential-like string' => ['credential=raw-secret-value'];

        yield 'password-like string' => ['password=raw-secret-value'];

        yield 'raw sql-like string' => ['SELECT * FROM users WHERE token = raw-secret-token'];

        yield 'url-like string' => ['https://example.test/path?token=raw-secret-token'];

        yield 'path-like string' => ['/home/user/project/.env'];

        yield 'header-like string' => ['X-Correlation-ID: 01ARZ3NDEKTSV4RRFFQ69G5FAV'];

        yield 'string with surrounding whitespace' => [' 01ARZ3NDEKTSV4RRFFQ69G5FAV '];

        yield 'string with control character' => ["01ARZ3NDEKTSV4RRFFQ69G5FA\n"];
    }

    #[DataProvider('unsafeCorrelationIdProvider')]
    public function testProviderReturnsNullForUnsafeMalformedOrNonStringCorrelationIdValues(
        mixed $value,
    ): void {
        $store = new ContextStore();
        $provider = new CorrelationIdProvider($store);

        $store->set(ContextKeys::CORRELATION_ID, $value);

        self::assertNull($provider->correlationId());

        self::assertTrue($store->has(ContextKeys::CORRELATION_ID));
        self::assertSame(
            [
                ContextKeys::CORRELATION_ID => $value,
            ],
            $store->all(),
            'CorrelationIdProvider must not normalize, rewrite, remove, or mutate unsafe context values.',
        );
    }

    public function testProviderDoesNotNormalizeLowercaseUlidLikeValue(): void
    {
        $store = new ContextStore();
        $provider = new CorrelationIdProvider($store);

        $store->set(ContextKeys::CORRELATION_ID, '01arz3ndektsv4rrffq69g5fav');

        self::assertNull($provider->correlationId());
        self::assertSame(
            [
                ContextKeys::CORRELATION_ID => '01arz3ndektsv4rrffq69g5fav',
            ],
            $store->all(),
        );
    }
}
