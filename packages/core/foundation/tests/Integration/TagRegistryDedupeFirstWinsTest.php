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

final class TagRegistryDedupeFirstWinsTest extends TestCase
{
    public function testDuplicateServiceForSameTagKeepsFirstOccurrence(): void
    {
        $registry = new TagRegistry();

        $registry->add(
            tag: 'kernel.reset',
            serviceId: 'service.stateful',
            priority: 10,
            meta: ['source' => 'first'],
        );

        $registry->add(
            tag: 'kernel.reset',
            serviceId: 'service.stateful',
            priority: 1000,
            meta: ['source' => 'second'],
        );

        $services = $registry->all('kernel.reset');

        self::assertCount(1, $services);
        self::assertSame('service.stateful', $services[0]->id());
        self::assertSame(10, $services[0]->priority());
        self::assertSame(['source' => 'first'], $services[0]->meta());
    }

    public function testDuplicateRegistrationDoesNotChangeCanonicalOrder(): void
    {
        $registry = new TagRegistry();

        $registry->add('kernel.reset', 'service.duplicate', -10);
        $registry->add('kernel.reset', 'service.top', 100);
        $registry->add('kernel.reset', 'service.duplicate', 1000);

        self::assertSame(
            [
                'service.top',
                'service.duplicate',
            ],
            self::idsFrom($registry->all('kernel.reset')),
        );

        self::assertSame(
            [
                100,
                -10,
            ],
            self::prioritiesFrom($registry->all('kernel.reset')),
        );
    }

    public function testDedupeAppliesPerTagOnly(): void
    {
        $registry = new TagRegistry();

        $registry->add('kernel.reset', 'service.shared', 10, ['tag' => 'reset']);
        $registry->add('kernel.stateful', 'service.shared', 100, ['tag' => 'stateful']);

        $resetServices = $registry->all('kernel.reset');
        $statefulServices = $registry->all('kernel.stateful');

        self::assertCount(1, $resetServices);
        self::assertCount(1, $statefulServices);

        self::assertSame('service.shared', $resetServices[0]->id());
        self::assertSame(10, $resetServices[0]->priority());
        self::assertSame(['tag' => 'reset'], $resetServices[0]->meta());

        self::assertSame('service.shared', $statefulServices[0]->id());
        self::assertSame(100, $statefulServices[0]->priority());
        self::assertSame(['tag' => 'stateful'], $statefulServices[0]->meta());
    }

    public function testFirstWinsIsIndependentFromLaterLowerPriorityDuplicates(): void
    {
        $registry = new TagRegistry();

        $registry->add('http.middleware.app', 'service.middleware', 100, ['version' => 'first']);
        $registry->add('http.middleware.app', 'service.other', 50);
        $registry->add('http.middleware.app', 'service.middleware', -100, ['version' => 'second']);

        $services = $registry->all('http.middleware.app');

        self::assertSame(
            [
                'service.middleware',
                'service.other',
            ],
            self::idsFrom($services),
        );

        self::assertSame(100, $services[0]->priority());
        self::assertSame(['version' => 'first'], $services[0]->meta());
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
