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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;

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
                moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                    FoundationServiceProvider::class,
                    KernelServiceProvider::class,
                    ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowProvider::class,
                ]),
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

    private static function assertBaseContextKeysAreAbsent(ContextStore $contextStore): void
    {
        self::assertFalse($contextStore->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_TYPE));
    }
}

final class ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->classService(
                ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder::class,
                ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder::class,
            )
            ->classService(
                id: ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowResetService::class,
                class: ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowResetService::class,
                arguments: [
                    ContainerValueReference::service(ContextStore::class),
                    ContainerValueReference::service(
                        ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowRecorder::class,
                    ),
                ],
            )
            ->classService(
                id: ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowIdGenerator::class,
                class: ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowIdGenerator::class,
                arguments: [
                    '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                ],
            )
            ->alias(
                IdGeneratorInterface::class,
                ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowIdGenerator::class,
            )
            ->classService(
                id: ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowCorrelationIdProvider::class,
                class: ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowCorrelationIdProvider::class,
                arguments: [
                    '01B7X3NDEKTSV4RRFFQ69G5FAV',
                ],
            )
            ->alias(
                CorrelationIdProviderInterface::class,
                ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowCorrelationIdProvider::class,
            )
            ->tag(
                tag: ReservedTags::KERNEL_RESET,
                serviceId: ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowResetService::class,
            );
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
