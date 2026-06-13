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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\Tags as FoundationTags;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowTest extends TestCase
{
    public function testArtifactOnlyBootKernelRuntimeTriggersResetExactlyOncePerUnitOfWork(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-only-kernel-runtime-reset-once');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                containerDescriptors: self::containerDescriptors(),
            );

            $container = ArtifactPipelineTestSupport::runtimeContainerFromArtifacts($root);

            $runtime = $container->get(KernelRuntime::class);
            $recorder = $container->get(ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder::class);
            $contextStore = $container->get(ContextStore::class);

            self::assertInstanceOf(KernelRuntime::class, $runtime);
            self::assertInstanceOf(ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder::class, $recorder);
            self::assertInstanceOf(ContextStore::class, $contextStore);

            $firstResult = $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($contextStore, $recorder): string {
                    $recorder->events[] = 'body:first';

                    self::assertTrue($contextStore->has(ContextKeys::CORRELATION_ID));
                    self::assertTrue($contextStore->has(ContextKeys::UOW_ID));
                    self::assertTrue($contextStore->has(ContextKeys::UOW_TYPE));

                    return 'first-body-value';
                },
            );

            self::assertSame('first-body-value', $firstResult);
            self::assertSame(1, $recorder->resetCount);
            self::assertSame(
                [
                    'body:first',
                    'reset',
                ],
                $recorder->events,
            );
            self::assertBaseContextKeysAreAbsent($contextStore);

            $secondResult = $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($contextStore, $recorder): string {
                    $recorder->events[] = 'body:second';

                    self::assertTrue($contextStore->has(ContextKeys::CORRELATION_ID));
                    self::assertTrue($contextStore->has(ContextKeys::UOW_ID));
                    self::assertTrue($contextStore->has(ContextKeys::UOW_TYPE));

                    return 'second-body-value';
                },
            );

            self::assertSame('second-body-value', $secondResult);
            self::assertSame(2, $recorder->resetCount);
            self::assertSame(
                [
                    'body:first',
                    'reset',
                    'body:second',
                    'reset',
                ],
                $recorder->events,
            );
            self::assertBaseContextKeysAreAbsent($contextStore);
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function containerDescriptors(): array
    {
        return [
            [
                'kind' => 'parameter',
                'name' => 'reset.tag',
                'value' => FoundationTags::KERNEL_RESET,
            ],
            [
                'kind' => 'service.class',
                'id' => ContextStore::class,
                'class' => ContextStore::class,
                'shared' => true,
            ],
            [
                'kind' => 'service.class',
                'id' => Stopwatch::class,
                'class' => Stopwatch::class,
                'shared' => true,
            ],
            [
                'kind' => 'service.class',
                'id' => UlidGenerator::class,
                'class' => UlidGenerator::class,
                'shared' => true,
            ],
            [
                'kind' => 'service.class',
                'id' => CorrelationIdGenerator::class,
                'class' => CorrelationIdGenerator::class,
                'shared' => true,
                'arguments' => [
                    [
                        'id' => UlidGenerator::class,
                        'type' => 'service',
                    ],
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => IdGeneratorInterface::class,
                'class' => ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowIdGenerator::class,
                'shared' => true,
                'arguments' => [
                    '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => CorrelationIdProviderInterface::class,
                'class' => ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowCorrelationIdProvider::class,
                'shared' => true,
                'arguments' => [
                    '01B7X3NDEKTSV4RRFFQ69G5FAV',
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => LoggerInterface::class,
                'class' => NullLogger::class,
                'shared' => true,
            ],
            [
                'kind' => 'service.class',
                'id' => TracerPortInterface::class,
                'class' => NoopTracer::class,
                'shared' => true,
            ],
            [
                'kind' => 'service.class',
                'id' => MeterPortInterface::class,
                'class' => NoopMeter::class,
                'shared' => true,
            ],
            [
                'kind' => 'service.class',
                'id' => ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder::class,
                'class' => ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder::class,
                'shared' => true,
            ],
            [
                'kind' => 'service.class',
                'id' => ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowResetService::class,
                'class' => ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowResetService::class,
                'shared' => true,
                'arguments' => [
                    [
                        'id' => ContextStore::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder::class,
                        'type' => 'service',
                    ],
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => ResetOrchestrator::class,
                'class' => ResetOrchestrator::class,
                'shared' => true,
                'arguments' => [
                    [
                        'id' => ContainerInterface::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => TagRegistry::class,
                        'type' => 'service',
                    ],
                    [
                        'name' => 'reset.tag',
                        'type' => 'parameter',
                    ],
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => HookInvoker::class,
                'class' => HookInvoker::class,
                'shared' => true,
                'arguments' => [
                    [
                        'id' => ContainerInterface::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => TagRegistry::class,
                        'type' => 'service',
                    ],
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => KernelRuntime::class,
                'class' => KernelRuntime::class,
                'shared' => true,
                'arguments' => [
                    [
                        'id' => ContextStore::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => ResetOrchestrator::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => Stopwatch::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => IdGeneratorInterface::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => CorrelationIdProviderInterface::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => CorrelationIdGenerator::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => HookInvoker::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => LoggerInterface::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => TracerPortInterface::class,
                        'type' => 'service',
                    ],
                    [
                        'id' => MeterPortInterface::class,
                        'type' => 'service',
                    ],
                ],
            ],
            [
                'kind' => 'tag',
                'tag' => FoundationTags::KERNEL_RESET,
                'serviceId' => ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowResetService::class,
                'priority' => 0,
                'meta' => [],
            ],
        ];
    }

    private static function assertBaseContextKeysAreAbsent(ContextStore $contextStore): void
    {
        self::assertFalse($contextStore->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_TYPE));
    }
}

final class ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public int $resetCount = 0;
}

final readonly class ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowResetService implements ResetInterface
{
    public function __construct(
        private ContextStore $contextStore,
        private ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        ++$this->recorder->resetCount;
        $this->recorder->events[] = 'reset';

        $this->contextStore->reset();
    }
}

final readonly class ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowIdGenerator implements IdGeneratorInterface
{
    /**
     * @param non-empty-string $id
     */
    public function __construct(
        private string $id,
    ) {
    }

    public function generate(): string
    {
        return $this->id;
    }
}

final readonly class ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowCorrelationIdProvider implements CorrelationIdProviderInterface
{
    /**
     * @param non-empty-string|null $correlationId
     */
    public function __construct(
        private ?string $correlationId,
    ) {
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }
}
