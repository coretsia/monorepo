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

use Coretsia\Foundation\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Context\Exception\ContextInvalidKeyException;
use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextStoreSafeWriteGuardBlocksForbiddenKeysTest extends TestCase
{
    /**
     * @return iterable<string,array{0:string}>
     */
    public static function unsafeNonCanonicalKeyProvider(): iterable
    {
        yield 'authorization' => ['authorization'];
        yield 'cookie' => ['cookie'];
        yield 'session id' => ['session_id'];
        yield 'token' => ['token'];
        yield 'raw sql' => ['raw_sql'];
    }

    #[DataProvider('unsafeNonCanonicalKeyProvider')]
    public function testUnsafeNonCanonicalKeysAreRejectedBeforeStorage(string $key): void
    {
        $store = new ContextStore();

        try {
            $store->set($key, 'safe-string-shape');
            self::fail('Expected context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
            self::assertSame('context-key-unknown', $exception->reason());
            self::assertSame('<key>', $exception->safeKey());
            self::assertSame('context-key-unknown: <key>', $exception->getMessage());
            self::assertStringNotContainsString($key, $exception->getMessage());
        }

        self::assertFalse($store->has($key));
        self::assertSame([], $store->all());
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function safeNonCanonicalKeyProvider(): iterable
    {
        yield 'raw headers' => ['headers'];
        yield 'raw request body' => ['request_body'];
        yield 'raw response body' => ['response_body'];
        yield 'email' => ['email'];
        yield 'phone' => ['phone'];
        yield 'full name' => ['full_name'];
    }

    #[DataProvider('safeNonCanonicalKeyProvider')]
    public function testSafeNonCanonicalKeysAreRejectedBeforeStorageWithSafeDiagnostics(string $key): void
    {
        $store = new ContextStore();

        try {
            $store->set($key, 'safe-string-shape');
            self::fail('Expected context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
            self::assertSame('context-key-unknown', $exception->reason());
            self::assertSame($key, $exception->safeKey());
            self::assertSame('context-key-unknown: ' . $key, $exception->getMessage());
        }

        self::assertFalse($store->has($key));
        self::assertSame([], $store->all());
    }

    public function testForbiddenValueShapeIsRejectedBeforeStorage(): void
    {
        $store = new ContextStore();

        try {
            $store->set(ContextKeys::USER_AGENT, ['metadata' => new \stdClass()]);
            self::fail('Expected context write rejection.');
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame('context-write-forbidden-object', $exception->reason());
            self::assertSame('user_agent.metadata', $exception->safePath());
            self::assertSame(
                'context-write-forbidden-object: value at user_agent.metadata',
                $exception->getMessage(),
            );

            self::assertStringNotContainsString('stdClass Object', $exception->getMessage());
            self::assertStringNotContainsString('stdClass', $exception->getMessage());
            self::assertStringNotContainsString('metadata' . \PHP_EOL, $exception->getMessage());
        }

        self::assertFalse($store->has(ContextKeys::USER_AGENT));
        self::assertSame([], $store->all());
    }

    public function testCallableLikeStringIsStillAcceptedAsPlainString(): void
    {
        $store = new ContextStore();

        $store->set(ContextKeys::UOW_TYPE, 'strlen');

        self::assertSame('strlen', $store->get(ContextKeys::UOW_TYPE));
        self::assertSame([ContextKeys::UOW_TYPE => 'strlen'], $store->all());
    }
}
