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
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class KernelRuntimeEnforcesSingleActiveUnitOfWorkTest extends TestCase
{
    public function testRunRejectsNestedRunWhileOuterUnitOfWorkIsActive(): void
    {
        $fixture = self::fixture();
        $runtime = $fixture['runtime'];
        $contextStore = $fixture['contextStore'];
        $recorder = $fixture['recorder'];
        $idGenerator = $fixture['idGenerator'];
        $resetService = $fixture['resetService'];

        $nestedBodyWasCalled = false;

        $result = $runtime->runUnitOfWork(
            UnitOfWorkType::HTTP,
            static function () use (
                $runtime,
                $contextStore,
                $recorder,
                $idGenerator,
                $resetService,
                &$nestedBodyWasCalled,
            ): string {
                $outerContext = self::baseContextValues($contextStore);

                $exception = self::assertKernelRuntimeFailure(
                    callback: static fn (): mixed => $runtime->runUnitOfWork(
                        UnitOfWorkType::CLI,
                        static function () use (&$nestedBodyWasCalled): void {
                            $nestedBodyWasCalled = true;
                        },
                    ),
                    expectedReason: KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
                );

                self::assertSame(
                    'CORETSIA_KERNEL_RUNTIME_ERROR: kernel-runtime-uow-already-active',
                    $exception->getMessage(),
                );
                self::assertSame(1, $idGenerator->generateCount);
                self::assertSame(1, $recorder->beforeCount);
                self::assertSame(0, $recorder->afterCount);
                self::assertSame(0, $resetService->resetCount);
                self::assertSame($outerContext, self::baseContextValues($contextStore));

                return 'outer-result';
            },
        );

        self::assertSame('outer-result', $result);
        self::assertFalse($nestedBodyWasCalled);
        self::assertSame(1, $resetService->resetCount);

        self::assertSame(
            'next-result',
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static fn (): string => 'next-result',
            ),
        );
        self::assertSame(2, $idGenerator->generateCount);
        self::assertSame(2, $recorder->beforeCount);
        self::assertSame(2, $recorder->afterCount);
        self::assertSame(2, $resetService->resetCount);
    }

    public function testRunRejectsNestedBeginWhileOuterUnitOfWorkIsActive(): void
    {
        $fixture = self::fixture();
        $runtime = $fixture['runtime'];
        $contextStore = $fixture['contextStore'];
        $recorder = $fixture['recorder'];
        $idGenerator = $fixture['idGenerator'];
        $resetService = $fixture['resetService'];

        $runtime->runUnitOfWork(
            UnitOfWorkType::HTTP,
            static function () use (
                $runtime,
                $contextStore,
                $recorder,
                $idGenerator,
                $resetService,
            ): void {
                $outerContext = self::baseContextValues($contextStore);

                self::assertKernelRuntimeFailure(
                    callback: static fn () => $runtime->beginUnitOfWork(UnitOfWorkType::CLI),
                    expectedReason: KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
                );

                self::assertSame(1, $idGenerator->generateCount);
                self::assertSame(1, $recorder->beforeCount);
                self::assertSame(0, $recorder->afterCount);
                self::assertSame(0, $resetService->resetCount);
                self::assertSame($outerContext, self::baseContextValues($contextStore));
            },
        );

        self::assertSame(1, $resetService->resetCount);
        self::assertSame(
            'next-result',
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static fn (): string => 'next-result',
            ),
        );
        self::assertSame(2, $idGenerator->generateCount);
        self::assertSame(2, $recorder->beforeCount);
        self::assertSame(2, $recorder->afterCount);
        self::assertSame(2, $resetService->resetCount);
    }

    public function testOpenLowLevelHandleRejectsRunUntilMatchingAfterCompletes(): void
    {
        $fixture = self::fixture();
        $runtime = $fixture['runtime'];
        $idGenerator = $fixture['idGenerator'];
        $resetService = $fixture['resetService'];

        $handleA = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);
        $rejectedBodyWasCalled = false;

        self::assertKernelRuntimeFailure(
            callback: static fn (): mixed => $runtime->runUnitOfWork(
                UnitOfWorkType::CLI,
                static function () use (&$rejectedBodyWasCalled): void {
                    $rejectedBodyWasCalled = true;
                },
            ),
            expectedReason: KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
        );

        self::assertFalse($rejectedBodyWasCalled);
        self::assertSame(1, $idGenerator->generateCount);
        self::assertSame(0, $resetService->resetCount);

        $runtime->afterUnitOfWork(
            handle: $handleA,
            outcome: Outcome::SUCCESS,
        );

        self::assertSame(1, $resetService->resetCount);
        self::assertSame(
            'run-after-a',
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static fn (): string => 'run-after-a',
            ),
        );
        self::assertSame(2, $idGenerator->generateCount);
        self::assertSame(2, $resetService->resetCount);
    }

    public function testOpenLowLevelHandleRejectsSecondBeginUntilMatchingAfterCompletes(): void
    {
        $fixture = self::fixture();
        $runtime = $fixture['runtime'];
        $contextStore = $fixture['contextStore'];
        $idGenerator = $fixture['idGenerator'];
        $resetService = $fixture['resetService'];

        $handleA = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);
        $outerContext = self::baseContextValues($contextStore);

        self::assertKernelRuntimeFailure(
            callback: static fn () => $runtime->beginUnitOfWork(UnitOfWorkType::CLI),
            expectedReason: KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
        );

        self::assertSame(1, $idGenerator->generateCount);
        self::assertSame(0, $resetService->resetCount);
        self::assertSame($outerContext, self::baseContextValues($contextStore));

        $runtime->afterUnitOfWork(
            handle: $handleA,
            outcome: Outcome::SUCCESS,
        );

        $handleB = $runtime->beginUnitOfWork(UnitOfWorkType::CLI);

        self::assertSame(2, $idGenerator->generateCount);

        $runtime->afterUnitOfWork(
            handle: $handleB,
            outcome: Outcome::SUCCESS,
        );

        self::assertSame(2, $resetService->resetCount);
    }

    public function testRejectedNestedStartDoesNotReleaseOuterLifecycle(): void
    {
        $fixture = self::fixture();
        $runtime = $fixture['runtime'];
        $contextStore = $fixture['contextStore'];
        $idGenerator = $fixture['idGenerator'];
        $resetService = $fixture['resetService'];

        $runtime->runUnitOfWork(
            UnitOfWorkType::HTTP,
            static function () use ($runtime, $contextStore, $idGenerator, $resetService): void {
                $outerContext = self::baseContextValues($contextStore);

                self::assertKernelRuntimeFailure(
                    callback: static fn (): mixed => $runtime->runUnitOfWork(
                        UnitOfWorkType::CLI,
                        static fn (): null => null,
                    ),
                    expectedReason: KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
                );

                self::assertKernelRuntimeFailure(
                    callback: static fn () => $runtime->beginUnitOfWork(UnitOfWorkType::QUEUE),
                    expectedReason: KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
                );

                self::assertSame(1, $idGenerator->generateCount);
                self::assertSame(0, $resetService->resetCount);
                self::assertSame($outerContext, self::baseContextValues($contextStore));
            },
        );

        self::assertSame(1, $resetService->resetCount);
        self::assertSame(
            'after-outer',
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static fn (): string => 'after-outer',
            ),
        );
        self::assertSame(2, $idGenerator->generateCount);
        self::assertSame(2, $resetService->resetCount);
    }

    public function testLifecycleRemainsActiveUntilResetCompletes(): void
    {
        $fixture = self::fixture();
        $runtime = $fixture['runtime'];
        $idGenerator = $fixture['idGenerator'];
        $resetService = $fixture['resetService'];

        $nestedBodyWasCalled = false;

        $resetService->setDuringFirstReset(
            static function () use ($runtime, $idGenerator, &$nestedBodyWasCalled): void {
                self::assertKernelRuntimeFailure(
                    callback: static fn (): mixed => $runtime->runUnitOfWork(
                        UnitOfWorkType::CLI,
                        static function () use (&$nestedBodyWasCalled): void {
                            $nestedBodyWasCalled = true;
                        },
                    ),
                    expectedReason: KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
                );

                self::assertSame(1, $idGenerator->generateCount);
            },
        );

        self::assertSame(
            'outer-result',
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static fn (): string => 'outer-result',
            ),
        );

        self::assertFalse($nestedBodyWasCalled);
        self::assertSame(1, $resetService->resetCount);
        self::assertSame(
            'next-result',
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static fn (): string => 'next-result',
            ),
        );
        self::assertSame(2, $idGenerator->generateCount);
        self::assertSame(2, $resetService->resetCount);
    }

    public function testActiveLifecycleRejectionPrecedesNestedUnitOfWorkValidation(): void
    {
        $fixture = self::fixture();
        $runtime = $fixture['runtime'];
        $idGenerator = $fixture['idGenerator'];
        $resetService = $fixture['resetService'];

        $handleA = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);

        self::assertSame(1, $idGenerator->generateCount);

        self::assertKernelRuntimeFailure(
            callback: static fn (): mixed => $runtime->runUnitOfWork(
                'invalid-unit-of-work-type',
                static fn (): null => null,
            ),
            expectedReason: KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
        );

        self::assertSame(1, $idGenerator->generateCount);
        self::assertSame(0, $resetService->resetCount);

        $runtime->afterUnitOfWork(
            handle: $handleA,
            outcome: Outcome::SUCCESS,
        );

        self::assertKernelRuntimeFailure(
            callback: static fn (): mixed => $runtime->runUnitOfWork(
                'invalid-unit-of-work-type',
                static fn (): null => null,
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_TYPE,
        );

        self::assertSame(1, $idGenerator->generateCount);
        self::assertSame(1, $resetService->resetCount);
        self::assertSame(
            'ok',
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static fn (): string => 'ok',
            ),
        );
        self::assertSame(2, $idGenerator->generateCount);
        self::assertSame(2, $resetService->resetCount);
    }

    public function testFailedBeginBeforeResetResponsibilityReleasesLifecycleGate(): void
    {
        $fixture = self::fixture();
        $runtime = $fixture['runtime'];
        $recorder = $fixture['recorder'];
        $idGenerator = $fixture['idGenerator'];
        $resetService = $fixture['resetService'];

        self::assertKernelRuntimeFailure(
            callback: static fn () => $runtime->beginUnitOfWork('invalid-unit-of-work-type'),
            expectedReason: KernelRuntimeException::REASON_INVALID_TYPE,
        );

        self::assertSame(0, $idGenerator->generateCount);
        self::assertSame(0, $recorder->beforeCount);
        self::assertSame(0, $resetService->resetCount);

        $handleB = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);

        self::assertSame(1, $idGenerator->generateCount);
        self::assertSame(1, $recorder->beforeCount);

        $runtime->afterUnitOfWork(
            handle: $handleB,
            outcome: Outcome::SUCCESS,
        );

        self::assertSame(1, $resetService->resetCount);
    }

    public function testLowLevelCompletionRemainsActiveUntilResetCompletes(): void
    {
        $fixture = self::fixture();
        $runtime = $fixture['runtime'];
        $idGenerator = $fixture['idGenerator'];
        $resetService = $fixture['resetService'];

        $handleA = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);
        $nestedBodyWasCalled = false;

        $resetService->setDuringFirstReset(
            static function () use ($runtime, $idGenerator, &$nestedBodyWasCalled): void {
                self::assertKernelRuntimeFailure(
                    callback: static fn (): mixed => $runtime->runUnitOfWork(
                        UnitOfWorkType::CLI,
                        static function () use (&$nestedBodyWasCalled): void {
                            $nestedBodyWasCalled = true;
                        },
                    ),
                    expectedReason: KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE,
                );

                self::assertSame(1, $idGenerator->generateCount);
            },
        );

        $result = $runtime->afterUnitOfWork(
            handle: $handleA,
            outcome: Outcome::SUCCESS,
        );

        self::assertSame(UnitOfWorkType::HTTP, $result['type']);
        self::assertSame(Outcome::SUCCESS, $result['outcome']);
        self::assertFalse($nestedBodyWasCalled);
        self::assertSame(1, $resetService->resetCount);

        self::assertSame(
            'next-result',
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static fn (): string => 'next-result',
            ),
        );
        self::assertSame(2, $idGenerator->generateCount);
        self::assertSame(2, $resetService->resetCount);
    }

    /**
     * @return array{
     *     runtime: KernelRuntime,
     *     contextStore: ContextStore,
     *     recorder: KernelRuntimeEnforcesSingleActiveUnitOfWorkRecorder,
     *     idGenerator: KernelRuntimeEnforcesSingleActiveUnitOfWorkIdGenerator,
     *     resetService: KernelRuntimeEnforcesSingleActiveUnitOfWorkResetService
     * }
     */
    private static function fixture(): array
    {
        $contextStore = new ContextStore();
        $recorder = new KernelRuntimeEnforcesSingleActiveUnitOfWorkRecorder();
        $idGenerator = new KernelRuntimeEnforcesSingleActiveUnitOfWorkIdGenerator();
        $resetService = new KernelRuntimeEnforcesSingleActiveUnitOfWorkResetService($contextStore);

        $container = new KernelRuntimeEnforcesSingleActiveUnitOfWorkContainer([
            KernelRuntimeEnforcesSingleActiveUnitOfWorkBeforeHook::class =>
                new KernelRuntimeEnforcesSingleActiveUnitOfWorkBeforeHook($recorder),
            KernelRuntimeEnforcesSingleActiveUnitOfWorkAfterHook::class =>
                new KernelRuntimeEnforcesSingleActiveUnitOfWorkAfterHook($recorder),
            KernelRuntimeEnforcesSingleActiveUnitOfWorkResetService::class => $resetService,
        ]);

        $hookRegistry = new TagRegistry();
        $hookRegistry->add(
            ReservedTags::KERNEL_HOOK_BEFORE_UOW,
            KernelRuntimeEnforcesSingleActiveUnitOfWorkBeforeHook::class,
        );
        $hookRegistry->add(
            ReservedTags::KERNEL_HOOK_AFTER_UOW,
            KernelRuntimeEnforcesSingleActiveUnitOfWorkAfterHook::class,
        );

        $resetRegistry = new TagRegistry();
        $resetRegistry->add(
            ReservedTags::KERNEL_RESET,
            KernelRuntimeEnforcesSingleActiveUnitOfWorkResetService::class,
        );

        $runtime = new KernelRuntime(
            contextStore: $contextStore,
            resetOrchestrator: new ResetOrchestrator(
                container: $container,
                tagRegistry: $resetRegistry,
            ),
            stopwatch: new Stopwatch(),
            uowIds: $idGenerator,
            correlationIdProvider: new KernelRuntimeEnforcesSingleActiveUnitOfWorkCorrelationIdProvider(
                'corr-001',
            ),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker(
                container: $container,
                tags: $hookRegistry,
            ),
            logger: new NullLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );

        return [
            'runtime' => $runtime,
            'contextStore' => $contextStore,
            'recorder' => $recorder,
            'idGenerator' => $idGenerator,
            'resetService' => $resetService,
        ];
    }

    /**
     * @return array{correlationId:mixed,uowId:mixed,uowType:mixed}
     */
    private static function baseContextValues(ContextStore $contextStore): array
    {
        return [
            'correlationId' => $contextStore->get(ContextKeys::CORRELATION_ID),
            'uowId' => $contextStore->get(ContextKeys::UOW_ID),
            'uowType' => $contextStore->get(ContextKeys::UOW_TYPE),
        ];
    }

    /**
     * @param callable(): mixed $callback
     */
    private static function assertKernelRuntimeFailure(
        callable $callback,
        string $expectedReason,
    ): KernelRuntimeException {
        try {
            $callback();
        } catch (KernelRuntimeException $exception) {
            self::assertSame(KernelRuntimeException::ERROR_CODE, $exception->errorCode());
            self::assertSame($expectedReason, $exception->reason());
            self::assertSame(
                KernelRuntimeException::ERROR_CODE . ': ' . $expectedReason,
                $exception->getMessage(),
            );

            return $exception;
        }

        self::fail('Expected KernelRuntimeException.');
    }
}

final class KernelRuntimeEnforcesSingleActiveUnitOfWorkRecorder
{
    public int $beforeCount = 0;
    public int $afterCount = 0;
}

final readonly class KernelRuntimeEnforcesSingleActiveUnitOfWorkBeforeHook implements BeforeUowHookInterface
{
    public function __construct(
        private KernelRuntimeEnforcesSingleActiveUnitOfWorkRecorder $recorder,
    ) {
    }

    public function beforeUow(array $context): void
    {
        ++$this->recorder->beforeCount;
    }
}

final readonly class KernelRuntimeEnforcesSingleActiveUnitOfWorkAfterHook implements AfterUowHookInterface
{
    public function __construct(
        private KernelRuntimeEnforcesSingleActiveUnitOfWorkRecorder $recorder,
    ) {
    }

    public function afterUow(array $context, array $result): void
    {
        ++$this->recorder->afterCount;
    }
}

final class KernelRuntimeEnforcesSingleActiveUnitOfWorkResetService implements ResetInterface
{
    private ?\Closure $duringFirstReset = null;
    private bool $duringFirstResetConsumed = false;

    public int $resetCount = 0;

    public function __construct(
        private ContextStore $contextStore,
    ) {
    }

    public function setDuringFirstReset(\Closure $callback): void
    {
        $this->duringFirstReset = $callback;
    }

    public function reset(): void
    {
        ++$this->resetCount;

        $this->contextStore->reset();

        if (
            !$this->duringFirstResetConsumed
            && $this->duringFirstReset !== null
        ) {
            $this->duringFirstResetConsumed = true;

            ($this->duringFirstReset)();
        }
    }
}

final class KernelRuntimeEnforcesSingleActiveUnitOfWorkIdGenerator implements IdGeneratorInterface
{
    public int $generateCount = 0;

    public function generate(): string
    {
        ++$this->generateCount;

        return 'uow-' . $this->generateCount;
    }
}

final readonly class KernelRuntimeEnforcesSingleActiveUnitOfWorkCorrelationIdProvider implements CorrelationIdProviderInterface
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

final readonly class KernelRuntimeEnforcesSingleActiveUnitOfWorkContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new \RuntimeException('test-container-service-not-found');
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
