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
            self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
            self::assertSame('context-key-reserved: @foo', $exception->getMessage());
        }

        self::assertFalse($store->has('@foo'));
        self::assertSame([], $store->all());
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function reservedDirectiveLikeKeyProvider(): iterable
    {
        yield '@append' => ['@append'];
        yield '@replace' => ['@replace'];
        yield '@env' => ['@env'];
        yield '@nested.directive' => ['@nested.directive'];
    }

    #[DataProvider('reservedDirectiveLikeKeyProvider')]
    public function testDirectiveLikeAtPrefixedKeysFailDeterministically(string $key): void
    {
        $store = new ContextStore();

        try {
            $store->set($key, 'value');
            self::fail('Expected reserved context key rejection.');
        } catch (ContextInvalidKeyException $exception) {
            self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
            self::assertStringContainsString('context-key-reserved', $exception->getMessage());
            self::assertStringContainsString($key, $exception->getMessage());
        }

        self::assertFalse($store->has($key));
        self::assertSame([], $store->all());
    }
}
