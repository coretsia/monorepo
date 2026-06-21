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
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use PHPUnit\Framework\TestCase;

final class ResetOrchestratorUsesConfiguredResetTagTest extends TestCase
{
    public function testFoundationServiceProviderWiresOrchestratorWithConfiguredResetTag(): void
    {
        $recorder = new ResetOrchestratorConfiguredTagRecorder();

        $custom = new ResetOrchestratorConfiguredTagResettableService('custom', $recorder);
        $default = new ResetOrchestratorConfiguredTagResettableService('default', $recorder);

        $builder = new ContainerBuilder(config: self::configWithResetTag('custom.reset'));

        $builder->instance('service.reset.custom', $custom);
        $builder->instance('service.reset.default', $default);

        $builder->tag('custom.reset', 'service.reset.custom', 100);
        $builder->tag(ReservedTags::KERNEL_RESET, 'service.reset.default', 100);

        $builder->register(new FoundationServiceProvider());

        $orchestrator = $builder->build()->get(ResetOrchestrator::class);

        self::assertInstanceOf(ResetOrchestrator::class, $orchestrator);
        self::assertSame('custom.reset', $orchestrator->effectiveResetTag());

        $orchestrator->resetAll();

        self::assertSame(['custom'], $recorder->events());
        self::assertSame(1, $custom->resetCount());
        self::assertSame(0, $default->resetCount());
    }

    public function testFoundationServiceProviderFallsBackToKernelResetWhenResetTagIsAbsent(): void
    {
        $recorder = new ResetOrchestratorConfiguredTagRecorder();

        $service = new ResetOrchestratorConfiguredTagResettableService('default', $recorder);

        $builder = new ContainerBuilder(config: self::configWithoutResetTag());

        $builder->instance('service.reset.default', $service);
        $builder->tag(ReservedTags::KERNEL_RESET, 'service.reset.default');

        $builder->register(new FoundationServiceProvider());

        $orchestrator = $builder->build()->get(ResetOrchestrator::class);

        self::assertInstanceOf(ResetOrchestrator::class, $orchestrator);
        self::assertSame(ReservedTags::KERNEL_RESET, $orchestrator->effectiveResetTag());

        $orchestrator->resetAll();

        self::assertSame(['default'], $recorder->events());
        self::assertSame(1, $service->resetCount());
    }

    /**
     * @return array<string, mixed>
     */
    private static function configWithResetTag(string $tag): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
                'reset' => [
                    'tag' => $tag,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function configWithoutResetTag(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
                'reset' => [],
            ],
        ];
    }
}

final class ResetOrchestratorConfiguredTagRecorder
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

final class ResetOrchestratorConfiguredTagResettableService implements ResetInterface
{
    private int $resetCount = 0;

    public function __construct(
        private readonly string $id,
        private readonly ResetOrchestratorConfiguredTagRecorder $recorder,
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
