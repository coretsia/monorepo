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

namespace Coretsia\Platform\Worker\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Worker\Communication\WorkerChildReadinessChannel;
use Coretsia\Platform\Worker\Communication\WorkerControlClient;
use Coretsia\Platform\Worker\Communication\WorkerControlProtocol;
use Coretsia\Platform\Worker\Communication\WorkerControlServer;
use Coretsia\Platform\Worker\Communication\WorkerControlTransport;
use Coretsia\Platform\Worker\Console\WorkerHealthCommand;
use Coretsia\Platform\Worker\Console\WorkerStartCommand;
use Coretsia\Platform\Worker\Console\WorkerStatusCommand;
use Coretsia\Platform\Worker\Console\WorkerStopCommand;
use Coretsia\Platform\Worker\Internal\TaskFactoryInternalInterface;
use Coretsia\Platform\Worker\Internal\WorkerControlClientInterface;
use Coretsia\Platform\Worker\Internal\WorkerProcessDriverResolverInterface;
use Coretsia\Platform\Worker\Internal\WorkerSupervisorInterface;
use Coretsia\Platform\Worker\Internal\WorkerSupervisorResolverInterface;
use Coretsia\Platform\Worker\Process\ContainerWorkerProcessDriverResolver;
use Coretsia\Platform\Worker\Process\Driver\PcntlWorkerProcessDriver;
use Coretsia\Platform\Worker\Process\Driver\ProcWorkerProcessDriver;
use Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostClient;
use Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostProtocol;
use Coretsia\Platform\Worker\Process\WorkerChildCommandBuilder;
use Coretsia\Platform\Worker\Process\WorkerForkIsolation;
use Coretsia\Platform\Worker\Runtime\WorkerLifecycleLocatorStore;
use Coretsia\Platform\Worker\Runtime\WorkerLifecycleLock;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;
use Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard;
use Coretsia\Platform\Worker\Runtime\WorkerStateStore;
use Coretsia\Platform\Worker\Runtime\WorkerStopSignal;
use Coretsia\Platform\Worker\Supervisor\ContainerWorkerSupervisorResolver;
use Coretsia\Platform\Worker\Supervisor\WorkerChildTable;
use Coretsia\Platform\Worker\Supervisor\WorkerSignalController;
use Coretsia\Platform\Worker\Supervisor\WorkerSupervisor;
use Coretsia\Platform\Worker\Task\HttpTaskFactory;
use Coretsia\Platform\Worker\Task\QueueTaskFactory;
use Coretsia\Platform\Worker\Worker\ApplicationWorker;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Platform worker DI wiring entrypoint.
 *
 * Runtime wiring is produced through one canonical declarative contribution.
 * Source registration delegates that contribution to ContainerBuilder, while
 * compile-mode orchestration may invoke define() directly.
 *
 * Wiring decisions:
 *
 * - WorkerServiceFactory is a shared stateless service;
 * - WorkerPoolSpec remains lazy and reads only the active config repository;
 * - TaskFactoryInternalInterface resolves only the task-factory service selected
 *   by the canonical WorkerPoolSpec task type;
 * - path-owning process services consume RuntimePathContext instead of
 *   BootstrapConfig or raw provider-owned path closures;
 * - WorkerStartCommand resolves WorkerSupervisorInterface lazily through
 *   WorkerSupervisorResolverInterface after runtime entrypoint validation;
 * - WorkerProcessDriverResolverInterface resolves only the selected concrete
 *   process driver after WorkerSupervisor is resolved;
 * - status, health, and stop commands resolve the active lifecycle locator
 *   through WorkerControlClientInterface, do not resolve WorkerPoolSpec, and do
 *   not read WorkerStateStore as a liveness authority;
 * - worker command metadata remains static and owner-approved;
 * - command tags preserve canonical TagRegistry ordering and first-wins policy.
 *
 * Definition production must not resolve services, execute worker lifecycle,
 * inspect environment or filesystem state, start processes, open sockets, write
 * runtime files, invoke KernelRuntimeInterface, or emit stdout/stderr.
 */
final class WorkerServiceProvider implements ServiceProviderInterface, ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();
        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->requireService(ConfigRepositoryInterface::class)
            ->requireService(ModulePlan::class)
            ->requireService(RuntimePathContext::class)
            ->requireService(WorkerPoolSpec::class)
            ->requireService(WorkerRuntimeEntrypointGuard::class)
            ->requireService(WorkerProcessDriverResolverInterface::class)
            ->requireService(ApplicationWorker::class)
            ->requireService(WorkerSupervisorInterface::class)
            ->requireService(WorkerControlClientInterface::class)
            ->requireService(QueueTaskFactory::class)
            ->requireService(HttpTaskFactory::class)
            ->classService(WorkerServiceFactory::class, WorkerServiceFactory::class)
            ->classService(StableJsonEncoder::class, StableJsonEncoder::class)
            ->classService(StableJsonDecoder::class, StableJsonDecoder::class)
            ->serviceMethodFactory(
                WorkerPoolSpec::class,
                WorkerServiceFactory::class,
                'workerPoolSpec',
                [
                    ContainerValueReference::service(ConfigRepositoryInterface::class),
                ],
            )
            ->serviceMethodFactory(
                WorkerRuntimeEntrypointGuard::class,
                WorkerServiceFactory::class,
                'workerRuntimeEntrypointGuard',
                [
                    ContainerValueReference::service(RuntimeEntrypointGuard::class),
                ],
            )
            ->serviceMethodFactory(
                WorkerStateStore::class,
                WorkerServiceFactory::class,
                'workerStateStore',
                [
                    ContainerValueReference::service(RuntimePathContext::class),
                    ContainerValueReference::service(StableJsonEncoder::class),
                    ContainerValueReference::service(StableJsonDecoder::class),
                ],
            )
            ->serviceMethodFactory(
                WorkerLifecycleLocatorStore::class,
                WorkerServiceFactory::class,
                'workerLifecycleLocatorStore',
                [
                    ContainerValueReference::service(RuntimePathContext::class),
                    ContainerValueReference::service(StableJsonEncoder::class),
                    ContainerValueReference::service(StableJsonDecoder::class),
                ],
            )
            ->serviceMethodFactory(
                WorkerLifecycleLock::class,
                WorkerServiceFactory::class,
                'workerLifecycleLock',
                [
                    ContainerValueReference::service(RuntimePathContext::class),
                ]
            )
            ->serviceMethodFactory(
                WorkerStopSignal::class,
                WorkerServiceFactory::class,
                'workerStopSignal',
                [
                    ContainerValueReference::service(RuntimePathContext::class),
                ]
            )
            ->serviceMethodFactory(
                WorkerControlTransport::class,
                WorkerServiceFactory::class,
                'workerControlTransport',
                [
                    ContainerValueReference::service(RuntimePathContext::class),
                ]
            )
            ->serviceMethodFactory(
                WorkerControlProtocol::class,
                WorkerServiceFactory::class,
                'workerControlProtocol',
                [
                    ContainerValueReference::service(StableJsonEncoder::class),
                    ContainerValueReference::service(StableJsonDecoder::class),
                ],
            )
            ->serviceMethodFactory(
                WorkerControlServer::class,
                WorkerServiceFactory::class,
                'workerControlServer',
                [
                    ContainerValueReference::service(WorkerControlTransport::class),
                    ContainerValueReference::service(WorkerControlProtocol::class),
                ],
            )
            ->serviceMethodFactory(
                WorkerControlClient::class,
                WorkerServiceFactory::class,
                'workerControlClient',
                [
                    ContainerValueReference::service(WorkerControlTransport::class),
                    ContainerValueReference::service(WorkerControlProtocol::class),
                    ContainerValueReference::service(WorkerLifecycleLock::class),
                    ContainerValueReference::service(WorkerLifecycleLocatorStore::class),
                    ContainerValueReference::service(TracerPortInterface::class),
                    ContainerValueReference::service(MeterPortInterface::class),
                    ContainerValueReference::service(LoggerInterface::class),
                    ContainerValueReference::service(Stopwatch::class),
                ],
            )
            ->alias(WorkerControlClientInterface::class, WorkerControlClient::class)
            ->serviceMethodFactory(
                WorkerChildReadinessChannel::class,
                WorkerServiceFactory::class,
                'workerChildReadinessChannel',
            )
            ->serviceMethodFactory(
                WorkerChildTable::class,
                WorkerServiceFactory::class,
                'workerChildTable',
            )
            ->serviceMethodFactory(
                WorkerSignalController::class,
                WorkerServiceFactory::class,
                'workerSignalController',
            )
            ->serviceMethodFactory(
                WorkerForkIsolation::class,
                WorkerServiceFactory::class,
                'workerForkIsolation',
                [
                    ContainerValueReference::service(WorkerLifecycleLock::class),
                    ContainerValueReference::service(WorkerControlServer::class),
                    ContainerValueReference::service(WorkerSignalController::class),
                    ContainerValueReference::service(WorkerChildTable::class),
                ],
            )
            ->serviceMethodFactory(
                QueueTaskFactory::class,
                WorkerServiceFactory::class,
                'queueTaskFactory',
            )
            ->serviceMethodFactory(
                HttpTaskFactory::class,
                WorkerServiceFactory::class,
                'httpTaskFactory',
                [
                    ContainerValueReference::service(ConfigRepositoryInterface::class),
                    ContainerValueReference::service(ModulePlan::class),
                    ContainerValueReference::service(WorkerRuntimeEntrypointGuard::class),
                    ContainerValueReference::service(ContainerInterface::class),
                ],
            )
            ->serviceMethodFactory(
                TaskFactoryInternalInterface::class,
                WorkerServiceFactory::class,
                'taskFactory',
                [
                    ContainerValueReference::service(WorkerPoolSpec::class),
                    ContainerValueReference::service(ContainerInterface::class),
                ],
            )
            ->serviceMethodFactory(
                ApplicationWorker::class,
                WorkerServiceFactory::class,
                'applicationWorker',
                [
                    ContainerValueReference::service(WorkerStopSignal::class),
                    ContainerValueReference::service(KernelRuntimeInterface::class),
                    ContainerValueReference::service(TaskFactoryInternalInterface::class),
                    ContainerValueReference::service(Stopwatch::class),
                    ContainerValueReference::service(TracerPortInterface::class),
                    ContainerValueReference::service(MeterPortInterface::class),
                ],
            )
            ->serviceMethodFactory(
                WorkerChildCommandBuilder::class,
                WorkerServiceFactory::class,
                'workerChildCommandBuilder',
                [
                    ContainerValueReference::service(RuntimePathContext::class),
                ],
            )
            ->serviceMethodFactory(
                PcntlWorkerProcessDriver::class,
                WorkerServiceFactory::class,
                'pcntlWorkerProcessDriver',
                [
                    ContainerValueReference::service(RuntimePathContext::class),
                    ContainerValueReference::service(WorkerChildCommandBuilder::class),
                    ContainerValueReference::service(WorkerChildReadinessChannel::class),
                    ContainerValueReference::service(WorkerForkIsolation::class),
                ],
            )
            ->serviceMethodFactory(
                WorkerProcProcessHostProtocol::class,
                WorkerServiceFactory::class,
                'workerProcProcessHostProtocol',
                [
                    ContainerValueReference::service(StableJsonEncoder::class),
                    ContainerValueReference::service(StableJsonDecoder::class),
                ],
            )
            ->serviceMethodFactory(
                WorkerProcProcessHostClient::class,
                WorkerServiceFactory::class,
                'workerProcProcessHostClient',
                [
                    ContainerValueReference::service(RuntimePathContext::class),
                    ContainerValueReference::service(WorkerProcProcessHostProtocol::class),
                ],
            )
            ->serviceMethodFactory(
                ProcWorkerProcessDriver::class,
                WorkerServiceFactory::class,
                'procWorkerProcessDriver',
                [
                    ContainerValueReference::service(RuntimePathContext::class),
                    ContainerValueReference::service(ConfigRepositoryInterface::class),
                    ContainerValueReference::service(WorkerChildCommandBuilder::class),
                    ContainerValueReference::service(WorkerChildReadinessChannel::class),
                    ContainerValueReference::service(WorkerProcProcessHostClient::class),
                ],
            )
            ->serviceMethodFactory(
                ContainerWorkerProcessDriverResolver::class,
                WorkerServiceFactory::class,
                'workerProcessDriverResolver',
                [
                    ContainerValueReference::service(ContainerInterface::class),
                ],
            )
            ->alias(
                WorkerProcessDriverResolverInterface::class,
                ContainerWorkerProcessDriverResolver::class,
            )
            ->serviceMethodFactory(
                WorkerSupervisor::class,
                WorkerServiceFactory::class,
                'workerSupervisor',
                [
                    ContainerValueReference::service(WorkerProcessDriverResolverInterface::class),
                    ContainerValueReference::service(WorkerLifecycleLock::class),
                    ContainerValueReference::service(WorkerLifecycleLocatorStore::class),
                    ContainerValueReference::service(WorkerControlServer::class),
                    ContainerValueReference::service(WorkerChildReadinessChannel::class),
                    ContainerValueReference::service(WorkerChildTable::class),
                    ContainerValueReference::service(WorkerSignalController::class),
                    ContainerValueReference::service(WorkerStateStore::class),
                    ContainerValueReference::service(WorkerStopSignal::class),
                    ContainerValueReference::service(TracerPortInterface::class),
                    ContainerValueReference::service(MeterPortInterface::class),
                    ContainerValueReference::service(LoggerInterface::class),
                    ContainerValueReference::service(Stopwatch::class),
                ],
            )
            ->alias(WorkerSupervisorInterface::class, WorkerSupervisor::class)
            ->classService(
                ContainerWorkerSupervisorResolver::class,
                ContainerWorkerSupervisorResolver::class,
                [
                    ContainerValueReference::service(ContainerInterface::class),
                ],
            )
            ->alias(WorkerSupervisorResolverInterface::class, ContainerWorkerSupervisorResolver::class)
            ->classService(
                WorkerStartCommand::class,
                WorkerStartCommand::class,
                [
                    ContainerValueReference::service(ConfigRepositoryInterface::class),
                    ContainerValueReference::service(ModulePlan::class),
                    ContainerValueReference::service(WorkerRuntimeEntrypointGuard::class),
                    ContainerValueReference::service(WorkerServiceFactory::class),
                    ContainerValueReference::service(WorkerSupervisorResolverInterface::class),
                ],
            )
            ->classService(
                WorkerStopCommand::class,
                WorkerStopCommand::class,
                [
                    ContainerValueReference::service(WorkerControlClientInterface::class),
                ]
            )
            ->classService(
                WorkerStatusCommand::class,
                WorkerStatusCommand::class,
                [
                    ContainerValueReference::service(WorkerControlClientInterface::class),
                ]
            )
            ->classService(
                WorkerHealthCommand::class,
                WorkerHealthCommand::class,
                [
                    ContainerValueReference::service(WorkerControlClientInterface::class),
                ]
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                WorkerStartCommand::class,
                meta: self::commandMeta(
                    WorkerStartCommand::NAME,
                    WorkerStartCommand::SUMMARY,
                    WorkerStartCommand::GROUP,
                    WorkerStartCommand::HIDDEN,
                    WorkerStartCommand::MODE,
                    WorkerStartCommand::ARGUMENTS,
                    WorkerStartCommand::OPTIONS,
                )
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                WorkerStopCommand::class,
                meta: self::commandMeta(
                    WorkerStopCommand::NAME,
                    WorkerStopCommand::SUMMARY,
                    WorkerStopCommand::GROUP,
                    WorkerStopCommand::HIDDEN,
                    WorkerStopCommand::MODE,
                    WorkerStopCommand::ARGUMENTS,
                    WorkerStopCommand::OPTIONS,
                )
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                WorkerStatusCommand::class,
                meta: self::commandMeta(
                    WorkerStatusCommand::NAME,
                    WorkerStatusCommand::SUMMARY,
                    WorkerStatusCommand::GROUP,
                    WorkerStatusCommand::HIDDEN,
                    WorkerStatusCommand::MODE,
                    WorkerStatusCommand::ARGUMENTS,
                    WorkerStatusCommand::OPTIONS,
                )
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                WorkerHealthCommand::class,
                meta: self::commandMeta(
                    WorkerHealthCommand::NAME,
                    WorkerHealthCommand::SUMMARY,
                    WorkerHealthCommand::GROUP,
                    WorkerHealthCommand::HIDDEN,
                    WorkerHealthCommand::MODE,
                    WorkerHealthCommand::ARGUMENTS,
                    WorkerHealthCommand::OPTIONS,
                )
            );
    }

    /**
     * @param list<array<string, mixed>> $arguments
     * @param list<array<string, mixed>> $options
     *
     * @return array{
     *     name: string,
     *     summary: string,
     *     group: string,
     *     hidden: bool,
     *     mode: string,
     *     arguments: list<array<string, mixed>>,
     *     options: list<array<string, mixed>>
     * }
     */
    private static function commandMeta(
        string $name,
        string $summary,
        string $group,
        bool $hidden,
        string $mode,
        array $arguments,
        array $options
    ): array {
        return compact(
            'name',
            'summary',
            'group',
            'hidden',
            'mode',
            'arguments',
            'options',
        );
    }
}
