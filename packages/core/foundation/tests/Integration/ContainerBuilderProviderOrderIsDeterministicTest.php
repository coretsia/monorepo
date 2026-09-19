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
use Coretsia\Foundation\Container\ServiceProviderInterface;
use PHPUnit\Framework\TestCase;

final class ContainerBuilderProviderOrderIsDeterministicTest extends TestCase
{
    public function testRegisterPreservesCallerSuppliedProviderOrderExactly(): void
    {
        $recorder = new ContainerBuilderProviderOrderRecorder();

        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->register(
            new ZuluContainerBuilderOrderProvider($recorder),
            new AlphaContainerBuilderOrderProvider($recorder),
            new MiddleContainerBuilderOrderProvider($recorder),
        );

        self::assertSame(
            [
                'zulu',
                'alpha',
                'middle',
            ],
            $recorder->events(),
        );
    }

    public function testRegisterProvidersPreservesIterableOrderExactly(): void
    {
        $recorder = new ContainerBuilderProviderOrderRecorder();

        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->registerProviders([
            new MiddleContainerBuilderOrderProvider($recorder),
            new ZuluContainerBuilderOrderProvider($recorder),
            new AlphaContainerBuilderOrderProvider($recorder),
        ]);

        self::assertSame(
            [
                'middle',
                'zulu',
                'alpha',
            ],
            $recorder->events(),
        );
    }

    public function testProviderOrderIsNotGloballySortedByProviderClassName(): void
    {
        $recorder = new ContainerBuilderProviderOrderRecorder();

        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->register(
            new ZuluContainerBuilderOrderProvider($recorder),
            new AlphaContainerBuilderOrderProvider($recorder),
        );

        self::assertSame(
            [
                'zulu',
                'alpha',
            ],
            $recorder->events(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function validConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
            ],
        ];
    }
}

final class ContainerBuilderProviderOrderRecorder
{
    /**
     * @var list<string>
     */
    private array $events = [];

    public function record(string $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }
}

final readonly class ZuluContainerBuilderOrderProvider implements ServiceProviderInterface
{
    public function __construct(
        private ContainerBuilderProviderOrderRecorder $recorder,
    ) {
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->recorder->record('zulu');
        $builder->set('service.order.zulu', 'zulu');
    }
}

final readonly class AlphaContainerBuilderOrderProvider implements ServiceProviderInterface
{
    public function __construct(
        private ContainerBuilderProviderOrderRecorder $recorder,
    ) {
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->recorder->record('alpha');
        $builder->set('service.order.alpha', 'alpha');
    }
}

final readonly class MiddleContainerBuilderOrderProvider implements ServiceProviderInterface
{
    public function __construct(
        private ContainerBuilderProviderOrderRecorder $recorder,
    ) {
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->recorder->record('middle');
        $builder->set('service.order.middle', 'middle');
    }
}
