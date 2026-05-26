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
use Coretsia\Foundation\Provider\Tags;
use Coretsia\Foundation\Runtime\Reset\ResetErrorCodes;
use Coretsia\Foundation\Runtime\Reset\ResetException;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use PHPUnit\Framework\TestCase;

final class ResetOrchestratorRejectsTaggedNonResettableServiceTest extends TestCase
{
    public function testRejectsTaggedNonResettableServiceWithTypedResetExceptionAndStableMessage(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->instance(
            'service.reset.invalid',
            new ResetOrchestratorRejectsNonResettableService(),
        );

        $builder->tag(Tags::KERNEL_RESET, 'service.reset.invalid');

        try {
            self::orchestratorFrom($builder)->resetAll();
        } catch (ResetException $exception) {
            self::assertServiceNotResettableException($exception);

            return;
        }

        self::fail('Expected ResetOrchestrator to fail with typed reset exception for tagged non-resettable service.');
    }

    public function testTypedHardFailIsDeterministicAndStopsAtFirstNonResettableServiceInRegistryOrder(): void
    {
        $recorder = new ResetOrchestratorRejectsRecorder();

        $before = new ResetOrchestratorRejectsResettableService('before', $recorder);
        $after = new ResetOrchestratorRejectsResettableService('after', $recorder);

        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->instance('service.reset.before', $before);
        $builder->instance('service.reset.invalid', new ResetOrchestratorRejectsNonResettableService());
        $builder->instance('service.reset.after', $after);

        $builder->tag(Tags::KERNEL_RESET, 'service.reset.before', 100);
        $builder->tag(Tags::KERNEL_RESET, 'service.reset.invalid', 50);
        $builder->tag(Tags::KERNEL_RESET, 'service.reset.after', 0);

        try {
            self::orchestratorFrom($builder)->resetAll();
        } catch (ResetException $exception) {
            self::assertServiceNotResettableException($exception);

            self::assertSame(['before'], $recorder->events());
            self::assertSame(1, $before->resetCount());
            self::assertSame(0, $after->resetCount());

            return;
        }

        self::fail('Expected ResetOrchestrator to stop at tagged non-resettable service.');
    }

    private static function assertServiceNotResettableException(ResetException $exception): void
    {
        self::assertSame(
            ResetErrorCodes::CORETSIA_RESET_SERVICE_NOT_RESETTABLE,
            $exception->code(),
        );
        self::assertSame($exception->code(), $exception->errorCode());
        self::assertSame('reset-not-resettable', $exception->reason());
        self::assertSame('reset-not-resettable', $exception->getMessage());

        $withoutPrevious = $exception->withoutPrevious();

        self::assertNotSame($exception, $withoutPrevious);
        self::assertSame($exception->code(), $withoutPrevious->code());
        self::assertSame($exception->errorCode(), $withoutPrevious->errorCode());
        self::assertSame($withoutPrevious->code(), $withoutPrevious->errorCode());
        self::assertSame($exception->reason(), $withoutPrevious->reason());
        self::assertSame('reset-not-resettable', $withoutPrevious->reason());
        self::assertSame($exception->getMessage(), $withoutPrevious->getMessage());
        self::assertNull($withoutPrevious->getPrevious());
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
                    'tag' => Tags::KERNEL_RESET,
                ],
            ],
        ];
    }
}

final class ResetOrchestratorRejectsRecorder
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

final class ResetOrchestratorRejectsResettableService implements ResetInterface
{
    private int $resetCount = 0;

    public function __construct(
        private readonly string $id,
        private readonly ResetOrchestratorRejectsRecorder $recorder,
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

final class ResetOrchestratorRejectsNonResettableService
{
}
