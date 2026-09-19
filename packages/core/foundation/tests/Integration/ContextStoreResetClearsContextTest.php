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
use PHPUnit\Framework\TestCase;

final class ContextStoreResetClearsContextTest extends TestCase
{
    public function testResetClearsAllStoredContext(): void
    {
        $store = new ContextStore();

        $store->set(ContextKeys::CORRELATION_ID, '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $store->set(ContextKeys::UOW_ID, 'uow-001');
        $store->set(ContextKeys::UOW_TYPE, 'http');

        self::assertTrue($store->has(ContextKeys::CORRELATION_ID));
        self::assertTrue($store->has(ContextKeys::UOW_ID));
        self::assertTrue($store->has(ContextKeys::UOW_TYPE));
        self::assertSame(
            [
                ContextKeys::CORRELATION_ID => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                ContextKeys::UOW_ID => 'uow-001',
                ContextKeys::UOW_TYPE => 'http',
            ],
            $store->all(),
        );

        $store->reset();

        self::assertFalse($store->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($store->has(ContextKeys::UOW_ID));
        self::assertFalse($store->has(ContextKeys::UOW_TYPE));
        self::assertNull($store->get(ContextKeys::CORRELATION_ID));
        self::assertSame([], $store->all());
    }

    public function testResetIsIdempotent(): void
    {
        $store = new ContextStore();

        $store->reset();
        $store->reset();

        self::assertSame([], $store->all());
    }

    public function testClearAndResetHaveSameEmptyStoreResult(): void
    {
        $store = new ContextStore();

        $store->set(ContextKeys::UOW_TYPE, 'worker');
        $store->clear();

        self::assertSame([], $store->all());

        $store->set(ContextKeys::UOW_TYPE, 'worker');
        $store->reset();

        self::assertSame([], $store->all());
    }
}
