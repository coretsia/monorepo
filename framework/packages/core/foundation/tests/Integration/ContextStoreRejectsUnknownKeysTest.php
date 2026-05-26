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

final class ContextStoreRejectsUnknownKeysTest extends TestCase
{
    public function testUnknownKeyFailsDeterministically(): void
    {
        $store = new ContextStore();

        try {
            $store->set('unknown_key', 'value');
            self::fail('Expected unknown context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSafeUnknownKeyException($exception, 'unknown_key');
        }

        self::assertFalse($store->has('unknown_key'));
        self::assertSame([], $store->all());
    }

    public function testUnknownKeyFailureMessageDoesNotContainRawValue(): void
    {
        $store = new ContextStore();

        try {
            $store->set('unknown_key', 'secret-token-value');
            self::fail('Expected unknown context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSafeUnknownKeyException($exception, 'unknown_key');
            self::assertStringNotContainsString('secret-token-value', $exception->getMessage());
        }

        self::assertSame([], $store->all());
    }

    /**
     * @return iterable<string,array{0:string,1:list<string>}>
     */
    public static function unsafeUnknownKeyProvider(): iterable
    {
        yield 'nested dotted key' => [
            'unknown.nested',
            ['unknown.nested'],
        ];

        yield 'authorization-like key' => [
            'Authorization',
            ['Authorization'],
        ];

        yield 'token-like key' => [
            'token',
            ['token'],
        ];

        yield 'credential-like key' => [
            'credential',
            ['credential'],
        ];

        yield 'password-like key' => [
            'password',
            ['password'],
        ];

        yield 'api-key-like key' => [
            'api_key',
            ['api_key'],
        ];

        yield 'sql-like key' => [
            'select',
            ['select'],
        ];

        yield 'url-like key' => [
            'https://example.test/path?token=secret',
            ['https://example.test/path?token=secret', 'https://example.test', 'token', 'secret'],
        ];

        yield 'absolute-path-like key' => [
            '/tmp/coretsia-secret',
            ['/tmp/coretsia-secret'],
        ];

        yield 'whitespace key' => [
            'unsafe key',
            ['unsafe key'],
        ];

        yield 'control-character key' => [
            "unsafe\nkey",
            ["unsafe\nkey"],
        ];

        yield 'overlong key' => [
            \str_repeat('a', 65),
            [\str_repeat('a', 65)],
        ];
    }

    /**
     * @param list<string> $forbiddenFragments
     */
    #[DataProvider('unsafeUnknownKeyProvider')]
    public function testUnsafeUnknownKeysDoNotLeakRawValues(
        string $key,
        array $forbiddenFragments,
    ): void {
        $store = new ContextStore();

        try {
            $store->set($key, 'value');
            self::fail('Expected unknown context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertUnsafeUnknownKeyException($exception, $forbiddenFragments);
        }

        self::assertFalse($store->has($key));
        self::assertSame([], $store->all());
    }

    public function testEmptyKeyFailsDeterministically(): void
    {
        $store = new ContextStore();

        try {
            $store->set('', 'value');
            self::fail('Expected empty context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
            self::assertSame('context-key-empty', $exception->reason());
            self::assertNull($exception->safeKey());
            self::assertSame('context-key-empty', $exception->getMessage());
        }

        self::assertSame([], $store->all());
    }

    private static function assertSafeUnknownKeyException(
        ContextInvalidKeyException $exception,
        string $key,
    ): void {
        self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
        self::assertSame('context-key-unknown', $exception->reason());
        self::assertSame($key, $exception->safeKey());
        self::assertSame('context-key-unknown: ' . $key, $exception->getMessage());
    }

    /**
     * @param list<string> $forbiddenFragments
     */
    private static function assertUnsafeUnknownKeyException(
        ContextInvalidKeyException $exception,
        array $forbiddenFragments,
    ): void {
        self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
        self::assertSame('context-key-unknown', $exception->reason());
        self::assertSame('<key>', $exception->safeKey());
        self::assertSame('context-key-unknown: <key>', $exception->getMessage());

        foreach ($forbiddenFragments as $fragment) {
            self::assertStringNotContainsString($fragment, $exception->getMessage());
            self::assertNotSame($fragment, $exception->safeKey());
        }
    }
}
