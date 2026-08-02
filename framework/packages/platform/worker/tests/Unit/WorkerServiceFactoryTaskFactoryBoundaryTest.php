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

namespace Coretsia\Platform\Worker\Tests\Unit;

use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Internal\TaskFactoryInternalInterface;
use Coretsia\Platform\Worker\Provider\WorkerServiceFactory;
use Coretsia\Platform\Worker\Task\QueueTaskFactory;
use Coretsia\Platform\Worker\Tests\Support\WorkerSpecFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class WorkerServiceFactoryTaskFactoryBoundaryTest extends TestCase
{
    public function testTaskFactoryResolvesOnlySelectedInternalFactory(): void
    {
        $queue = new QueueTaskFactory();
        $container = new RecordingTaskContainer([
            QueueTaskFactory::class => $queue,
        ]);

        $resolved = new WorkerServiceFactory()->taskFactory(
            WorkerSpecFactory::create([
                'task_type' => 'queue',
            ]),
            $container,
        );

        self::assertSame($queue, $resolved);
        self::assertSame([QueueTaskFactory::class], $container->requested);
        self::assertInstanceOf(TaskFactoryInternalInterface::class, $resolved);
    }

    public function testTaskFactoryDoesNotFallbackAcrossTaskTypes(): void
    {
        $container = new RecordingTaskContainer([
            QueueTaskFactory::class => new QueueTaskFactory(),
        ]);

        $this->expectException(
            WorkerStartFailedException::class,
        );

        new WorkerServiceFactory()->taskFactory(
            WorkerSpecFactory::create([
                'task_type' => 'http',
            ]),
            $container,
        );
    }
}

final class RecordingTaskContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $requested = [];

    /** @param array<string, object> $services */
    public function __construct(private readonly array $services)
    {
    }

    public function get(string $id): mixed
    {
        $this->requested[] = $id;

        if (!isset($this->services[$id])) {
            throw new \RuntimeException('service-missing');
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
