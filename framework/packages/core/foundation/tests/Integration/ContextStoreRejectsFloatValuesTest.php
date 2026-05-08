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
use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextStoreRejectsFloatValuesTest extends TestCase
{
    public function testNestedFloatFailsDeterministicallyWithSafePathOnly(): void
    {
        $store = new ContextStore();

        try {
            $store->set(ContextKeys::HOST, ['metadata' => ['items' => [['count' => 3.14]]]]);
            self::fail('Expected float value rejection.');
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                'context-write-forbidden-float: value at host.metadata.items[0].count',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('3.14', $exception->getMessage());
        }

        self::assertFalse($store->has(ContextKeys::HOST));
        self::assertSame([], $store->all());
    }

    /**
     * @return iterable<string,array{0:float,1:string}>
     */
    public static function forbiddenFloatProvider(): iterable
    {
        yield 'finite float' => [1.25, '1.25'];
        yield 'NaN' => [\NAN, 'NAN'];
        yield 'INF' => [\INF, 'INF'];
        yield '-INF' => [-\INF, '-INF'];
    }

    #[DataProvider('forbiddenFloatProvider')]
    public function testFloatVariantsFailWithSafeMessage(float $value, string $rawValueFragment): void
    {
        $store = new ContextStore();

        try {
            $store->set(ContextKeys::CLIENT_IP, ['network' => ['score' => $value]]);
            self::fail('Expected float value rejection.');
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertStringContainsString('context-write-forbidden-float', $exception->getMessage());
            self::assertStringContainsString('client_ip.network.score', $exception->getMessage());
            self::assertStringNotContainsString($rawValueFragment, $exception->getMessage());
        }

        self::assertFalse($store->has(ContextKeys::CLIENT_IP));
        self::assertSame([], $store->all());
    }

    public function testIntegerValuesRemainAllowed(): void
    {
        $store = new ContextStore();

        $store->set(ContextKeys::UOW_ID, ['parts' => [1, 2, 3]]);

        self::assertSame(['parts' => [1, 2, 3]], $store->get(ContextKeys::UOW_ID));
    }
}
