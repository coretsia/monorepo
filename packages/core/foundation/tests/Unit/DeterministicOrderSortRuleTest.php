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

use Coretsia\Foundation\Discovery\DeterministicOrder;
use Coretsia\Foundation\Tag\TaggedService;
use PHPUnit\Framework\TestCase;

final class DeterministicOrderSortRuleTest extends TestCase
{
    public function testCompareOrdersHigherPriorityBeforeLowerPriority(): void
    {
        self::assertLessThan(
            0,
            DeterministicOrder::compare(
                leftPriority: 100,
                leftId: 'service.low_name',
                rightPriority: 10,
                rightId: 'service.high_name',
            ),
        );

        self::assertGreaterThan(
            0,
            DeterministicOrder::compare(
                leftPriority: 10,
                leftId: 'service.high_name',
                rightPriority: 100,
                rightId: 'service.low_name',
            ),
        );
    }

    public function testCompareOrdersIdAscendingWhenPriorityIsEqual(): void
    {
        self::assertLessThan(
            0,
            DeterministicOrder::compare(
                leftPriority: 0,
                leftId: 'service.alpha',
                rightPriority: 0,
                rightId: 'service.beta',
            ),
        );

        self::assertGreaterThan(
            0,
            DeterministicOrder::compare(
                leftPriority: 0,
                leftId: 'service.beta',
                rightPriority: 0,
                rightId: 'service.alpha',
            ),
        );

        self::assertSame(
            0,
            DeterministicOrder::compare(
                leftPriority: 0,
                leftId: 'service.alpha',
                rightPriority: 0,
                rightId: 'service.alpha',
            ),
        );
    }

    public function testCompareUsesByteOrderStringComparisonForEqualPriority(): void
    {
        self::assertSame(
            \strcmp('service.A', 'service.a'),
            DeterministicOrder::compare(
                leftPriority: 0,
                leftId: 'service.A',
                rightPriority: 0,
                rightId: 'service.a',
            ),
        );

        self::assertSame(
            \strcmp('service.a10', 'service.a2'),
            DeterministicOrder::compare(
                leftPriority: 0,
                leftId: 'service.a10',
                rightPriority: 0,
                rightId: 'service.a2',
            ),
        );
    }

    public function testSortAppliesPriorityDescendingThenIdAscending(): void
    {
        $services = [
            new TaggedService(id: 'service.zeta', priority: 0),
            new TaggedService(id: 'service.beta', priority: 50),
            new TaggedService(id: 'service.alpha', priority: 50),
            new TaggedService(id: 'service.gamma', priority: 10),
            new TaggedService(id: 'service.delta', priority: -10),
        ];

        $sorted = DeterministicOrder::sort(
            $services,
            static fn (TaggedService $service): string => $service->id(),
            static fn (TaggedService $service): int => $service->priority(),
        );

        self::assertSame(
            [
                'service.alpha',
                'service.beta',
                'service.gamma',
                'service.zeta',
                'service.delta',
            ],
            self::idsFrom($sorted),
        );
    }

    public function testSortReturnsAList(): void
    {
        $sorted = DeterministicOrder::sort(
            [
                new TaggedService(id: 'service.b', priority: 0),
                new TaggedService(id: 'service.a', priority: 0),
            ],
            static fn (TaggedService $service): string => $service->id(),
            static fn (TaggedService $service): int => $service->priority(),
        );

        self::assertTrue(\array_is_list($sorted));
        self::assertSame(['service.a', 'service.b'], self::idsFrom($sorted));
    }

    /**
     * @param list<TaggedService> $services
     *
     * @return list<string>
     */
    private static function idsFrom(array $services): array
    {
        $ids = [];

        foreach ($services as $service) {
            $ids[] = $service->id();
        }

        return $ids;
    }
}
