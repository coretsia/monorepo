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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Context\ContextKeys;
use PHPUnit\Framework\TestCase;

final class ContextKeysAreStableContractTest extends TestCase
{
    /**
     * @return list<non-empty-string>
     */
    private static function expectedKeys(): array
    {
        return [
            'correlation_id',
            'uow_id',
            'uow_type',
            'client_ip',
            'scheme',
            'host',
            'path',
            'user_agent',
            'request_id',
            'path_template',
            'http_response_format',
            'actor_id',
            'tenant_id',
        ];
    }

    public function testCanonicalKeyListIsStableAndOrdered(): void
    {
        self::assertSame(self::expectedKeys(), ContextKeys::all());
    }

    public function testCanonicalConstantsMatchStableKeyValues(): void
    {
        self::assertSame('correlation_id', ContextKeys::CORRELATION_ID);
        self::assertSame('uow_id', ContextKeys::UOW_ID);
        self::assertSame('uow_type', ContextKeys::UOW_TYPE);

        self::assertSame('client_ip', ContextKeys::CLIENT_IP);
        self::assertSame('scheme', ContextKeys::SCHEME);
        self::assertSame('host', ContextKeys::HOST);
        self::assertSame('path', ContextKeys::PATH);
        self::assertSame('user_agent', ContextKeys::USER_AGENT);

        self::assertSame('request_id', ContextKeys::REQUEST_ID);
        self::assertSame('path_template', ContextKeys::PATH_TEMPLATE);
        self::assertSame('http_response_format', ContextKeys::HTTP_RESPONSE_FORMAT);
        self::assertSame('actor_id', ContextKeys::ACTOR_ID);
        self::assertSame('tenant_id', ContextKeys::TENANT_ID);
    }

    public function testCanonicalKeyListContainsNoDuplicates(): void
    {
        $keys = ContextKeys::all();

        self::assertSame(
            $keys,
            \array_values(\array_unique($keys)),
        );
    }

    public function testAllCanonicalKeysAreKnown(): void
    {
        foreach (self::expectedKeys() as $key) {
            self::assertTrue(
                ContextKeys::isKnown($key),
                \sprintf('Expected context key "%s" to be known.', $key),
            );
        }
    }

    public function testUnknownAndReservedKeysAreNotKnown(): void
    {
        self::assertFalse(ContextKeys::isKnown(''));
        self::assertFalse(ContextKeys::isKnown('unknown_key'));
        self::assertFalse(ContextKeys::isKnown('@foo'));
        self::assertFalse(ContextKeys::isKnown('@append'));
        self::assertFalse(ContextKeys::isKnown('@replace'));
    }

    public function testCanonicalKeysDoNotUseReservedAtPrefix(): void
    {
        foreach (ContextKeys::all() as $key) {
            self::assertStringStartsNotWith(
                '@',
                $key,
                \sprintf('Context key "%s" must not use the reserved @* namespace.', $key),
            );
        }
    }

    public function testCanonicalKeysUseStableLowercaseSnakeCaseAsciiShape(): void
    {
        foreach (ContextKeys::all() as $key) {
            self::assertMatchesRegularExpression(
                '/\A[a-z][a-z0-9_]*\z/',
                $key,
                \sprintf('Context key "%s" must be stable lowercase snake_case ASCII.', $key),
            );
        }
    }
}
