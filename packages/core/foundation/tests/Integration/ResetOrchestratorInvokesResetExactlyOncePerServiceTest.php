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

use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use PHPUnit\Framework\TestCase;

final class ResetOrchestratorInvokesResetExactlyOncePerServiceTest extends TestCase
{
    public function testInvokesResetExactlyOncePerTaggedResettableServiceInRegistryOrder(): void
    {
        $recorder = new ResetOrchestratorInvokesRecorder();

        $alpha = new ResetOrchestratorInvokesResettableService('alpha', $recorder);
        $beta = new ResetOrchestratorInvokesResettableService('beta', $recorder);
        $zeta = new ResetOrchestratorInvokesResettableService('zeta', $recorder);

        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->instance('service.reset.zeta', $zeta);
        $builder->instance('service.reset.beta', $beta);
        $builder->instance('service.reset.alpha', $alpha);

        $builder->tag(ReservedTags::KERNEL_RESET, 'service.reset.zeta', 0);
        $builder->tag(ReservedTags::KERNEL_RESET, 'service.reset.beta', 50);
        $builder->tag(ReservedTags::KERNEL_RESET, 'service.reset.alpha', 50);

        self::orchestratorFrom($builder)->resetAll();

        self::assertSame(
            [
                'alpha',
                'beta',
                'zeta',
            ],
            $recorder->events(),
        );

        self::assertSame(1, $alpha->resetCount());
        self::assertSame(1, $beta->resetCount());
        self::assertSame(1, $zeta->resetCount());
    }

    public function testEachResetCycleInvokesEachServiceOnceAgain(): void
    {
        $recorder = new ResetOrchestratorInvokesRecorder();

        $alpha = new ResetOrchestratorInvokesResettableService('alpha', $recorder);
        $beta = new ResetOrchestratorInvokesResettableService('beta', $recorder);

        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->instance('service.reset.beta', $beta);
        $builder->instance('service.reset.alpha', $alpha);

        $builder->tag(ReservedTags::KERNEL_RESET, 'service.reset.beta', 0);
        $builder->tag(ReservedTags::KERNEL_RESET, 'service.reset.alpha', 0);

        $orchestrator = self::orchestratorFrom($builder);

        $orchestrator->resetAll();
        $orchestrator->resetAll();

        self::assertSame(
            [
                'alpha',
                'beta',
                'alpha',
                'beta',
            ],
            $recorder->events(),
        );

        self::assertSame(2, $alpha->resetCount());
        self::assertSame(2, $beta->resetCount());
    }

    public function testEmptyDiscoveryListIsDeterministicNoop(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        self::orchestratorFrom($builder)->resetAll();

        self::assertTrue(true);
    }

    public function testResetExecutionDoesNotRequireAutowireConfigForExplicitInstances(): void
    {
        $recorder = new ResetOrchestratorInvokesRecorder();

        $service = new ResetOrchestratorInvokesResettableService('explicit', $recorder);

        $builder = new ContainerBuilder();

        $builder->instance('service.reset.explicit', $service);
        $builder->tag(ReservedTags::KERNEL_RESET, 'service.reset.explicit');

        self::orchestratorFrom($builder)->resetAll();

        self::assertSame(['explicit'], $recorder->events());
        self::assertSame(1, $service->resetCount());
    }

    private static function orchestratorFrom(ContainerBuilder $builder): ResetOrchestrator
    {
        return new ResetOrchestrator(
            container: $builder->build(),
            tagRegistry: $builder->tagRegistry(),
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
                'reset' => [
                    'tag' => ReservedTags::KERNEL_RESET,
                ],
            ],
        ];
    }
}

final class ResetOrchestratorInvokesRecorder
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

final class ResetOrchestratorInvokesResettableService implements ResetInterface
{
    private int $resetCount = 0;

    public function __construct(
        private readonly string $id,
        private readonly ResetOrchestratorInvokesRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        ++$this->resetCount;

        $this->recorder->record($this->id);
    }

    public function resetCount(): int
    {
        return $this->resetCount;
    }
}
