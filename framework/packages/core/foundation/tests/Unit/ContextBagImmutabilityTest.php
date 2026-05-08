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

namespace Coretsia\Foundation\Tests\Unit;

use Coretsia\Foundation\Context\ContextBag;
use Coretsia\Foundation\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use PHPUnit\Framework\TestCase;

final class ContextBagImmutabilityTest extends TestCase
{
    public function testContextBagDoesNotObserveOriginalArrayMutations(): void
    {
        $source = [
            ContextKeys::CORRELATION_ID => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ContextKeys::UOW_ID => [
                'parts' => [
                    'first',
                    'second',
                ],
            ],
        ];

        $bag = new ContextBag($source);

        $source[ContextKeys::CORRELATION_ID] = '01BRZ3NDEKTSV4RRFFQ69G5FAW';
        $source[ContextKeys::UOW_ID]['parts'][0] = 'changed';
        $source[ContextKeys::UOW_TYPE] = 'http';

        self::assertTrue($bag->has(ContextKeys::CORRELATION_ID));
        self::assertTrue($bag->has(ContextKeys::UOW_ID));
        self::assertFalse($bag->has(ContextKeys::UOW_TYPE));

        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $bag->get(ContextKeys::CORRELATION_ID));
        self::assertSame(
            [
                'parts' => [
                    'first',
                    'second',
                ],
            ],
            $bag->get(ContextKeys::UOW_ID),
        );
    }

    public function testContextStoreSnapshotDoesNotObserveLaterStoreMutations(): void
    {
        $store = new ContextStore();

        $store->set(ContextKeys::CORRELATION_ID, '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $store->set(ContextKeys::PATH_TEMPLATE, [
            'segments' => [
                'users',
                '{id}',
            ],
        ]);

        $snapshot = $store->snapshot();

        $store->set(ContextKeys::CORRELATION_ID, '01BRZ3NDEKTSV4RRFFQ69G5FAW');
        $store->set(ContextKeys::PATH_TEMPLATE, [
            'segments' => [
                'projects',
                '{id}',
            ],
        ]);
        $store->set(ContextKeys::UOW_TYPE, 'http');
        $store->clear();

        self::assertTrue($snapshot->has(ContextKeys::CORRELATION_ID));
        self::assertTrue($snapshot->has(ContextKeys::PATH_TEMPLATE));
        self::assertFalse($snapshot->has(ContextKeys::UOW_TYPE));

        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $snapshot->get(ContextKeys::CORRELATION_ID));
        self::assertSame(
            [
                'segments' => [
                    'users',
                    '{id}',
                ],
            ],
            $snapshot->get(ContextKeys::PATH_TEMPLATE),
        );

        self::assertSame([], $store->all());
    }

    public function testContextBagReadApisReturnCopies(): void
    {
        $bag = new ContextBag([
            ContextKeys::PATH_TEMPLATE => [
                'segments' => [
                    'users',
                    '{id}',
                ],
            ],
        ]);

        $all = $bag->all();
        $all[ContextKeys::PATH_TEMPLATE]['segments'][0] = 'changed-from-all';

        $value = $bag->get(ContextKeys::PATH_TEMPLATE);
        self::assertIsArray($value);

        $value['segments'][0] = 'changed-from-get';

        self::assertSame(
            [
                'segments' => [
                    'users',
                    '{id}',
                ],
            ],
            $bag->get(ContextKeys::PATH_TEMPLATE),
        );
    }
}
