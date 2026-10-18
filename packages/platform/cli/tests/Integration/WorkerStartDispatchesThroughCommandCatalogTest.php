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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Platform\Cli\Tests\Integration\Support\FakeWorkerSupervisor;
use Coretsia\Platform\Cli\Tests\Integration\Support\TaggedCommandApplicationFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkerComposedTestCase;
use Coretsia\Platform\Worker\Console\WorkerStartCommand;
use Coretsia\Platform\Worker\Internal\WorkerSupervisorInterface;
use Coretsia\Platform\Worker\Internal\WorkerSupervisorResolverInterface;
use Coretsia\Platform\Worker\Provider\WorkerServiceFactory;
use Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard;
use Psr\Container\ContainerInterface;

final class WorkerStartDispatchesThroughCommandCatalogTest extends WorkerComposedTestCase
{
    public function testWorkerStartBuffersReadinessUntilFakeForegroundSupervisorReturns(): void
    {
        $root = self::workerConsumer();
        try {
            [$plan, $catalog, $registry, $host] = self::composedWorkerHost($root);
            self::assertWorkerTags($catalog, $registry);
            $supervisor = new FakeWorkerSupervisor();
            $resolver = new class($supervisor) implements WorkerSupervisorResolverInterface {
                public int $calls = 0;

                public function __construct(private WorkerSupervisorInterface $supervisor)
                {
                }

                public function resolve(): WorkerSupervisorInterface
                {
                    ++$this->calls;
                    return $this->supervisor;
                }
            };
            $command = new WorkerStartCommand(
                $host->get(ConfigRepositoryInterface::class),
                $plan,
                new WorkerRuntimeEntrypointGuard(new RuntimeDriverResolver()),
                new WorkerServiceFactory(),
                $resolver,
            );
            $commandContainer = new class($command, $host) implements ContainerInterface {
                public int $workerResolutions = 0;

                public function __construct(private WorkerStartCommand $command, private ContainerInterface $host)
                {
                }

                public function get(string $id): mixed
                {
                    if ($id === WorkerStartCommand::class) {
                        ++$this->workerResolutions;
                        return $this->command;
                    }
                    return $this->host->get($id);
                }

                public function has(string $id): bool
                {
                    return $id === WorkerStartCommand::class || $this->host->has($id);
                }
            };
            $cli = new TaggedCommandApplicationFixture(
                $catalog,
                $commandContainer,
                $host->get(SensitiveDataRedactorInterface::class),
            );
            $rejected = $cli->run([WorkerStartCommand::NAME, '--target=worker', '--format=json']);
            self::assertSame(1, $rejected['exit']);
            self::assertSame(0, $commandContainer->workerResolutions);
            self::assertSame(0, $resolver->calls);
            self::assertSame(0, $supervisor->calls);
            self::assertSame(0, $cli->runtime->calls);

            $duringReadiness = null;
            $result = $cli->run(
                [WorkerStartCommand::NAME, '--format=json'],
                static function ($stdout, $stderr) use ($supervisor, &$duringReadiness): void {
                    $supervisor->afterReady = static function () use ($stdout, $stderr, &$duringReadiness): void {
                        $duringReadiness = [\ftell($stdout), \ftell($stderr)];
                    };
                },
            );
            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame('', $result['stderr']);
            self::assertSame([0, 0], $duringReadiness);
            self::assertSame(1, $supervisor->calls);
            self::assertTrue($supervisor->returned);
            self::assertSame(['supervisor-enter', 'readiness-reported', 'supervisor-shutdown'], $supervisor->events);
            self::assertSame(1, $resolver->calls);
            self::assertSame(1, $commandContainer->workerResolutions);
            self::assertSame(1, $cli->runtime->calls);
            self::assertSame(WorkerStartCommand::NAME, $cli->runtime->invocations[0]['attributes']['operation']);
            $document = TaggedCommandApplicationFixture::document($result);
            $record = $document['data']['records'][0]['payload'];
            self::assertSame('running', $record['status']);
            self::assertSame(2, $record['ready_worker_count']);
            self::assertSame(2, $record['worker_count']);
            self::assertSame(4242, $record['pid']);
            self::assertSame('tcp', $record['control_transport']);
            self::assertSame(1, \count($document['data']['records']));
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testWorkerStartContractStillOwnsBlockingSupervisorAndBufferedOutput(): void
    {
        $source = (string) \file_get_contents(
            self::repositoryRoot() . '/packages/platform/worker/src/Console/WorkerStartCommand.php',
        );
        self::assertStringContainsString('return $this->supervisor()->run(', $source);
        self::assertStringContainsString('$output->json(self::summary($state));', $source);
        $implementation = \preg_replace('~/\*.*?\*/~s', '', $source);
        self::assertIsString($implementation);
        foreach (['proc_open(', 'pcntl_fork(', 'fwrite(STDOUT', 'fwrite(STDERR', 'stream_socket_'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $implementation);
        }
    }
}
