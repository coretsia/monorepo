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

use Coretsia\Foundation\Discovery\DeterministicOrder;
use Coretsia\Foundation\Tag\TaggedService;
use PHPUnit\Framework\TestCase;

final class DeterministicOrderSortContractTest extends TestCase
{
    public function testCanonicalOrderIsPriorityDescThenByteOrderIdAscForDifferentInputOrders(): void
    {
        $expected = [
            'service.A',
            'service.a10',
            'service.a2',
            'service.alpha',
            'service.beta',
            'service.zero',
            'service.low',
        ];

        foreach (self::inputOrders() as $input) {
            $sorted = DeterministicOrder::sort(
                $input,
                static fn (TaggedService $service): string => $service->id(),
                static fn (TaggedService $service): int => $service->priority(),
            );

            self::assertSame($expected, self::idsFrom($sorted));
        }
    }

    public function testCanonicalSortDoesNotDependOnLocaleCollation(): void
    {
        $services = [
            new TaggedService(id: 'service.a', priority: 0),
            new TaggedService(id: 'service.A', priority: 0),
            new TaggedService(id: 'service.a2', priority: 0),
            new TaggedService(id: 'service.a10', priority: 0),
        ];

        $sorted = DeterministicOrder::sort(
            $services,
            static fn (TaggedService $service): string => $service->id(),
            static fn (TaggedService $service): int => $service->priority(),
        );

        self::assertSame(
            [
                'service.A',
                'service.a',
                'service.a10',
                'service.a2',
            ],
            self::idsFrom($sorted),
        );
    }

    public function testCanonicalSortPreservesAllEntriesWithoutDedupe(): void
    {
        $services = [
            new TaggedService(id: 'service.same', priority: 10, meta: ['source' => 'first']),
            new TaggedService(id: 'service.same', priority: 10, meta: ['source' => 'second']),
            new TaggedService(id: 'service.other', priority: 10),
        ];

        $sorted = DeterministicOrder::sort(
            $services,
            static fn (TaggedService $service): string => $service->id(),
            static fn (TaggedService $service): int => $service->priority(),
        );

        self::assertCount(3, $sorted);
        self::assertSame(
            [
                'service.other',
                'service.same',
                'service.same',
            ],
            self::idsFrom($sorted),
        );
    }

    /**
     * @return list<list<TaggedService>>
     */
    private static function inputOrders(): array
    {
        $canonicalSet = [
            new TaggedService(id: 'service.zero', priority: 0),
            new TaggedService(id: 'service.beta', priority: 10),
            new TaggedService(id: 'service.low', priority: -10),
            new TaggedService(id: 'service.a2', priority: 50),
            new TaggedService(id: 'service.alpha', priority: 10),
            new TaggedService(id: 'service.A', priority: 50),
            new TaggedService(id: 'service.a10', priority: 50),
        ];

        return [
            $canonicalSet,
            [
                $canonicalSet[6],
                $canonicalSet[5],
                $canonicalSet[4],
                $canonicalSet[3],
                $canonicalSet[2],
                $canonicalSet[1],
                $canonicalSet[0],
            ],
            [
                $canonicalSet[2],
                $canonicalSet[0],
                $canonicalSet[4],
                $canonicalSet[6],
                $canonicalSet[1],
                $canonicalSet[5],
                $canonicalSet[3],
            ],
        ];
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
