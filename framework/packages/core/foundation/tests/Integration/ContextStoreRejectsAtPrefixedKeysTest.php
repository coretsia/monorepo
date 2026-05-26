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

use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Context\Exception\ContextInvalidKeyException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextStoreRejectsAtPrefixedKeysTest extends TestCase
{
    public function testAtPrefixedKeyFailsDeterministically(): void
    {
        $store = new ContextStore();

        try {
            $store->set('@foo', 'value');
            self::fail('Expected reserved context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSafeReservedKeyException($exception, '@foo');
        }

        self::assertFalse($store->has('@foo'));
        self::assertSame([], $store->all());
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function safeReservedDirectiveLikeKeyProvider(): iterable
    {
        yield '@append' => ['@append'];
        yield '@replace' => ['@replace'];
        yield '@env' => ['@env'];
    }

    #[DataProvider('safeReservedDirectiveLikeKeyProvider')]
    public function testSafeDirectiveLikeAtPrefixedKeysFailDeterministically(string $key): void
    {
        $store = new ContextStore();

        try {
            $store->set($key, 'value');
            self::fail('Expected reserved context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSafeReservedKeyException($exception, $key);
        }

        self::assertFalse($store->has($key));
        self::assertSame([], $store->all());
    }

    /**
     * @return iterable<string,array{0:string,1:list<string>}>
     */
    public static function unsafeReservedDirectiveLikeKeyProvider(): iterable
    {
        yield 'nested directive key' => [
            '@nested.directive',
            ['@nested.directive'],
        ];

        yield 'authorization-like key' => [
            '@Authorization',
            ['@Authorization', 'Authorization'],
        ];

        yield 'token-like key' => [
            '@token',
            ['@token', 'token'],
        ];

        yield 'credential-like key' => [
            '@credential',
            ['@credential', 'credential'],
        ];

        yield 'sql-like key' => [
            '@select',
            ['@select', 'select'],
        ];

        yield 'url-like key' => [
            '@https://example.test/path?token=secret',
            ['@https://example.test/path?token=secret', 'https://example.test', 'token', 'secret'],
        ];

        yield 'absolute-path-like key' => [
            '@/tmp/coretsia-secret',
            ['@/tmp/coretsia-secret', '/tmp/coretsia-secret'],
        ];

        yield 'whitespace key' => [
            '@unsafe key',
            ['@unsafe key'],
        ];

        yield 'control-character key' => [
            "@unsafe\nkey",
            ["@unsafe\nkey"],
        ];

        yield 'overlong key' => [
            '@' . \str_repeat('a', 65),
            ['@' . \str_repeat('a', 65)],
        ];
    }

    /**
     * @param list<string> $forbiddenFragments
     */
    #[DataProvider('unsafeReservedDirectiveLikeKeyProvider')]
    public function testUnsafeDirectiveLikeAtPrefixedKeysDoNotLeakRawValues(
        string $key,
        array $forbiddenFragments,
    ): void {
        $store = new ContextStore();

        try {
            $store->set($key, 'value');
            self::fail('Expected reserved context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertUnsafeReservedKeyException($exception, $forbiddenFragments);
        }

        self::assertFalse($store->has($key));
        self::assertSame([], $store->all());
    }

    private static function assertSafeReservedKeyException(
        ContextInvalidKeyException $exception,
        string $key,
    ): void {
        self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
        self::assertSame('context-key-reserved', $exception->reason());
        self::assertSame($key, $exception->safeKey());
        self::assertSame('context-key-reserved: ' . $key, $exception->getMessage());
    }

    /**
     * @param list<string> $forbiddenFragments
     */
    private static function assertUnsafeReservedKeyException(
        ContextInvalidKeyException $exception,
        array $forbiddenFragments,
    ): void {
        self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
        self::assertSame('context-key-reserved', $exception->reason());
        self::assertSame('<key>', $exception->safeKey());
        self::assertSame('context-key-reserved: <key>', $exception->getMessage());

        foreach ($forbiddenFragments as $fragment) {
            self::assertStringNotContainsString($fragment, $exception->getMessage());
            self::assertNotSame($fragment, $exception->safeKey());
        }
    }
}
