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

use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Context\Exception\ContextInvalidKeyException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextInvalidKeyDiagnosticsAreSafeContractTest extends TestCase
{
    /**
     * @return iterable<string,array{0:string}>
     */
    public static function safeUnknownKeyProvider(): iterable
    {
        yield 'simple underscore key' => ['unknown_key'];
        yield 'camel-like key' => ['UnknownKey'];
        yield 'leading underscore key' => ['_unknown'];
        yield 'alphanumeric key' => ['unknown123'];
    }

    #[DataProvider('safeUnknownKeyProvider')]
    public function testSafeUnknownKeyDiagnosticsRemainStableForConservativeSafeKeys(string $key): void
    {
        self::assertRejectedContextKey(
            key: $key,
            expectedReason: 'context-key-unknown',
            expectedSafeKey: $key,
            expectedMessage: 'context-key-unknown: ' . $key,
            forbiddenFragments: ['secret-value-not-written'],
        );
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function safeReservedKeyProvider(): iterable
    {
        yield 'simple reserved key' => ['@foo'];
        yield 'append directive-like key' => ['@append'];
        yield 'replace directive-like key' => ['@replace'];
        yield 'env directive-like key' => ['@env'];
        yield 'underscore reserved key' => ['@_internal'];
    }

    #[DataProvider('safeReservedKeyProvider')]
    public function testSafeReservedKeyDiagnosticsRemainStableForConservativeSafeAtPrefixedKeys(string $key): void
    {
        self::assertRejectedContextKey(
            key: $key,
            expectedReason: 'context-key-reserved',
            expectedSafeKey: $key,
            expectedMessage: 'context-key-reserved: ' . $key,
            forbiddenFragments: ['secret-value-not-written'],
        );
    }

    /**
     * @return iterable<string,array{0:string,1:list<string>}>
     */
    public static function unsafeUnknownKeyProvider(): iterable
    {
        yield 'dotted unknown key' => [
            'unknown.nested',
            ['unknown.nested'],
        ];

        yield 'authorization-like key' => [
            'Authorization',
            ['Authorization'],
        ];

        yield 'bearer-like key' => [
            'Bearer',
            ['Bearer'],
        ];

        yield 'token-like key' => [
            'token',
            ['token'],
        ];

        yield 'cookie-like key' => [
            'cookie_session',
            ['cookie_session', 'cookie', 'session'],
        ];

        yield 'credential-like key' => [
            'credential',
            ['credential'],
        ];

        yield 'api-key-like key' => [
            'api_key',
            ['api_key'],
        ];

        yield 'password-like key' => [
            'password',
            ['password'],
        ];

        yield 'secret-like key' => [
            'secret',
            ['secret'],
        ];

        yield 'sql-like key' => [
            'select',
            ['select'],
        ];

        yield 'sql-fragment-like key' => [
            'drop_table',
            ['drop_table', 'drop'],
        ];

        yield 'url-like key' => [
            'https://example.test/path?token=secret',
            ['https://example.test/path?token=secret', 'https://example.test', 'token', 'secret'],
        ];

        yield 'absolute-path-like key' => [
            '/home/user/project/.env',
            ['/home/user/project/.env', '.env'],
        ];

        yield 'whitespace key' => [
            'unsafe key',
            ['unsafe key'],
        ];

        yield 'control-character key' => [
            "unsafe\nkey",
            ["unsafe\nkey", "\n"],
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
    public function testUnsafeUnknownKeysAreReplacedWithStablePlaceholder(
        string $key,
        array $forbiddenFragments,
    ): void {
        self::assertRejectedContextKey(
            key: $key,
            expectedReason: 'context-key-unknown',
            expectedSafeKey: '<key>',
            expectedMessage: 'context-key-unknown: <key>',
            forbiddenFragments: $forbiddenFragments,
        );
    }

    /**
     * @return iterable<string,array{0:string,1:list<string>}>
     */
    public static function unsafeReservedKeyProvider(): iterable
    {
        yield 'dotted reserved key' => [
            '@nested.directive',
            ['@nested.directive'],
        ];

        yield 'authorization-like reserved key' => [
            '@Authorization',
            ['@Authorization', 'Authorization'],
        ];

        yield 'bearer-like reserved key' => [
            '@Bearer',
            ['@Bearer', 'Bearer'],
        ];

        yield 'token-like reserved key' => [
            '@token',
            ['@token', 'token'],
        ];

        yield 'cookie-like reserved key' => [
            '@cookie',
            ['@cookie', 'cookie'],
        ];

        yield 'credential-like reserved key' => [
            '@credential',
            ['@credential', 'credential'],
        ];

        yield 'api-key-like reserved key' => [
            '@api_key',
            ['@api_key', 'api_key'],
        ];

        yield 'password-like reserved key' => [
            '@password',
            ['@password', 'password'],
        ];

        yield 'secret-like reserved key' => [
            '@secret',
            ['@secret', 'secret'],
        ];

        yield 'sql-like reserved key' => [
            '@select',
            ['@select', 'select'],
        ];

        yield 'url-like reserved key' => [
            '@https://example.test/path?token=secret',
            ['@https://example.test/path?token=secret', 'https://example.test', 'token', 'secret'],
        ];

        yield 'absolute-path-like reserved key' => [
            '@/home/user/project/.env',
            ['@/home/user/project/.env', '/home/user/project/.env', '.env'],
        ];

        yield 'whitespace reserved key' => [
            '@unsafe key',
            ['@unsafe key'],
        ];

        yield 'control-character reserved key' => [
            "@unsafe\nkey",
            ["@unsafe\nkey", "\n"],
        ];

        yield 'overlong reserved key' => [
            '@' . \str_repeat('a', 65),
            ['@' . \str_repeat('a', 65)],
        ];
    }

    /**
     * @param list<string> $forbiddenFragments
     */
    #[DataProvider('unsafeReservedKeyProvider')]
    public function testUnsafeReservedKeysAreReplacedWithStablePlaceholder(
        string $key,
        array $forbiddenFragments,
    ): void {
        self::assertRejectedContextKey(
            key: $key,
            expectedReason: 'context-key-reserved',
            expectedSafeKey: '<key>',
            expectedMessage: 'context-key-reserved: <key>',
            forbiddenFragments: $forbiddenFragments,
        );
    }

    /**
     * @param list<string> $forbiddenFragments
     */
    private static function assertRejectedContextKey(
        string $key,
        string $expectedReason,
        ?string $expectedSafeKey,
        string $expectedMessage,
        array $forbiddenFragments,
    ): void {
        $store = new ContextStore();

        try {
            $store->set($key, 'secret-value-not-written');
            self::fail('Expected ContextInvalidKeyException was not thrown.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
            self::assertSame($expectedReason, $exception->reason());
            self::assertSame($expectedSafeKey, $exception->safeKey());
            self::assertSame($expectedMessage, $exception->getMessage());
            self::assertSame(0, $exception->getCode());

            foreach ($forbiddenFragments as $fragment) {
                self::assertStringNotContainsString(
                    $fragment,
                    $exception->getMessage(),
                    'Context invalid-key diagnostics must not leak unsafe rejected keys.',
                );

                if ($expectedSafeKey !== $fragment) {
                    self::assertNotSame(
                        $fragment,
                        $exception->safeKey(),
                        'ContextInvalidKeyException::safeKey() must not expose unsafe rejected keys.',
                    );
                }
            }
        }

        self::assertFalse($store->has($key));
        self::assertSame([], $store->all());
    }
}
