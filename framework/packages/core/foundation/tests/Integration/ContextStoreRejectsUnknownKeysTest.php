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
            self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
            self::assertSame('context-key-unknown: unknown_key', $exception->getMessage());
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
            self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
            self::assertStringContainsString('context-key-unknown', $exception->getMessage());
            self::assertStringContainsString('unknown_key', $exception->getMessage());
            self::assertStringNotContainsString('secret-token-value', $exception->getMessage());
        }

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
            self::assertSame('context-key-empty', $exception->getMessage());
        }

        self::assertSame([], $store->all());
    }
}
