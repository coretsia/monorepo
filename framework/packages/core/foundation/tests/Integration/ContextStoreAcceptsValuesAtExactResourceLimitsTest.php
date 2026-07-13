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

final class ContextStoreAcceptsValuesAtExactResourceLimitsTest extends TestCase
{
    public function testAcceptsListAndMapAtExactDepthLimit(): void
    {
        $store = new ContextStore();

        $list = self::nestedList(8);
        $map = self::nestedMap(8);

        $store->set(
            ContextKeys::PATH_TEMPLATE,
            $list,
        );

        self::assertSame(
            $list,
            $store->get(ContextKeys::PATH_TEMPLATE),
        );

        $store->set(
            ContextKeys::PATH_TEMPLATE,
            $map,
        );

        self::assertSame(
            $map,
            $store->get(ContextKeys::PATH_TEMPLATE),
        );
    }

    public function testAcceptsListAndMapAtExactNodeLimit(): void
    {
        $store = new ContextStore();

        $list = \array_fill(0, 256, 'value');
        $map = [];

        for ($i = 0; $i < 256; $i++) {
            $map[\sprintf('node_%03d', $i)] = 'value';
        }

        $store->set(
            ContextKeys::PATH_TEMPLATE,
            $list,
        );

        self::assertSame(
            $list,
            $store->get(ContextKeys::PATH_TEMPLATE),
        );

        $store->set(
            ContextKeys::PATH_TEMPLATE,
            $map,
        );

        self::assertSame(
            $map,
            $store->get(ContextKeys::PATH_TEMPLATE),
        );
    }

    public function testAcceptsStringsAtExactByteLimit(): void
    {
        $store = new ContextStore();

        $string = \str_repeat('x', 4096);
        $key = \str_repeat('k', 4096);

        $store->set(
            ContextKeys::PATH_TEMPLATE,
            $string,
        );

        self::assertSame(
            $string,
            $store->get(ContextKeys::PATH_TEMPLATE),
        );

        $store->set(
            ContextKeys::PATH_TEMPLATE,
            [
                $key => 'value',
            ],
        );

        self::assertSame(
            [
                $key => 'value',
            ],
            $store->get(ContextKeys::PATH_TEMPLATE),
        );
    }

    private static function nestedList(int $depth): array
    {
        $value = 'leaf';

        for ($i = 0; $i < $depth; $i++) {
            $value = [$value];
        }

        return $value;
    }

    private static function nestedMap(int $depth): array
    {
        $value = 'leaf';

        for ($i = 0; $i < $depth; $i++) {
            $value = [
                'level' => $value,
            ];
        }

        return $value;
    }
}
