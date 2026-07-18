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

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use PHPUnit\Framework\TestCase;

final class ContainerDefinitionApplierPreservesTagFirstWinsTest extends TestCase
{
    public function testDuplicateTagOperationKeepsFirstPriorityAndMetadata(): void
    {
        $definitions = new ContainerDefinitionBuilder()
            ->tag(
                tag: 'test.handlers',
                serviceId: 'service.handler',
                priority: 10,
                meta: [
                    'source' => 'first',
                ],
            )
            ->tag(
                tag: 'test.handlers',
                serviceId: 'service.handler',
                priority: 1000,
                meta: [
                    'source' => 'second',
                ],
            )
            ->build();

        $builder = new ContainerBuilder(config: []);
        $builder->applyDefinitions($definitions);

        $services = $builder->tagRegistry()->all('test.handlers');

        self::assertCount(1, $services);
        self::assertSame('service.handler', $services[0]->id());
        self::assertSame(10, $services[0]->priority());
        self::assertSame(
            [
                'source' => 'first',
            ],
            $services[0]->meta(),
        );
    }

    public function testFirstWinsIsPreservedAcrossMergedProviderSets(): void
    {
        $firstProviderSet = new ContainerDefinitionBuilder()
            ->tag(
                tag: 'test.handlers',
                serviceId: 'service.duplicate',
                priority: -10,
                meta: [
                    'provider' => 'first',
                ],
            )
            ->build();

        $secondProviderSet = new ContainerDefinitionBuilder()
            ->tag(
                tag: 'test.handlers',
                serviceId: 'service.top',
                priority: 100,
            )
            ->tag(
                tag: 'test.handlers',
                serviceId: 'service.duplicate',
                priority: 1000,
                meta: [
                    'provider' => 'second',
                ],
            )
            ->build();

        $builder = new ContainerBuilder(config: []);
        $builder->applyDefinitions(
            ContainerDefinitionSet::merge(
                $firstProviderSet,
                $secondProviderSet,
            ),
        );

        $services = $builder->tagRegistry()->all('test.handlers');

        self::assertCount(2, $services);
        self::assertSame('service.top', $services[0]->id());
        self::assertSame(100, $services[0]->priority());

        self::assertSame('service.duplicate', $services[1]->id());
        self::assertSame(-10, $services[1]->priority());
        self::assertSame(
            [
                'provider' => 'first',
            ],
            $services[1]->meta(),
        );
    }
}
