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

use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Foundation\Tag\TagRegistry;
use PHPUnit\Framework\TestCase;

final class TagRegistryReturnsDeterministicOrderTest extends TestCase
{
    public function testAllReturnsTaggedServicesInCanonicalDeterministicOrder(): void
    {
        $registry = new TagRegistry();

        $registry->add('http.middleware.app', 'service.zeta', 0);
        $registry->add('http.middleware.app', 'service.beta', 50);
        $registry->add('http.middleware.app', 'service.alpha', 50);
        $registry->add('http.middleware.app', 'service.gamma', 10);
        $registry->add('http.middleware.app', 'service.delta', -10);

        $services = $registry->all('http.middleware.app');

        self::assertContainsOnlyInstancesOf(TaggedService::class, $services);
        self::assertSame(
            [
                'service.alpha',
                'service.beta',
                'service.gamma',
                'service.zeta',
                'service.delta',
            ],
            self::idsFrom($services),
        );

        self::assertSame([50, 50, 10, 0, -10], self::prioritiesFrom($services));
    }

    public function testAllReturnsTheSameOrderForDifferentInsertionOrders(): void
    {
        $first = new TagRegistry();
        $first->add('kernel.reset', 'service.low', -10);
        $first->add('kernel.reset', 'service.beta', 10);
        $first->add('kernel.reset', 'service.alpha', 10);
        $first->add('kernel.reset', 'service.top', 100);

        $second = new TagRegistry();
        $second->add('kernel.reset', 'service.top', 100);
        $second->add('kernel.reset', 'service.alpha', 10);
        $second->add('kernel.reset', 'service.beta', 10);
        $second->add('kernel.reset', 'service.low', -10);

        self::assertSame(
            self::idsFrom($first->all('kernel.reset')),
            self::idsFrom($second->all('kernel.reset')),
        );

        self::assertSame(
            [
                'service.top',
                'service.alpha',
                'service.beta',
                'service.low',
            ],
            self::idsFrom($first->all('kernel.reset')),
        );
    }

    public function testAllUsesByteOrderIdComparisonForEqualPriority(): void
    {
        $registry = new TagRegistry();

        $registry->add('cli.command', 'service.a2', 0);
        $registry->add('cli.command', 'service.A', 0);
        $registry->add('cli.command', 'service.a10', 0);

        self::assertSame(
            [
                'service.A',
                'service.a10',
                'service.a2',
            ],
            self::idsFrom($registry->all('cli.command')),
        );
    }

    public function testUnknownValidTagReturnsEmptyList(): void
    {
        $registry = new TagRegistry();

        self::assertSame([], $registry->all('kernel.reset'));
    }

    public function testTagNamesAreReturnedInByteOrderForDiagnostics(): void
    {
        $registry = new TagRegistry();

        $registry->add('kernel.reset', 'service.reset');
        $registry->add('cli.command', 'service.command');
        $registry->add('http.middleware.app', 'service.middleware');

        self::assertSame(
            [
                'cli.command',
                'http.middleware.app',
                'kernel.reset',
            ],
            $registry->tagNames(),
        );
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

    /**
     * @param list<TaggedService> $services
     *
     * @return list<int>
     */
    private static function prioritiesFrom(array $services): array
    {
        $priorities = [];

        foreach ($services as $service) {
            $priorities[] = $service->priority();
        }

        return $priorities;
    }
}
