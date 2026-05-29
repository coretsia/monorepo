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
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
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
use Coretsia\Kernel\Provider\Tags as KernelTags;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class KernelRuntimeAlwaysResetsAfterUowTest extends TestCase
{
    public function testRunUnitOfWorkReturnsBodyValueWhenBodyAfterAndResetSucceed(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();

        $runtime = self::runtime($recorder);

        $bodyArguments = null;

        $result = $runtime->runUnitOfWork(
            UnitOfWorkType::HTTP,
            static function (...$arguments) use ($recorder, &$bodyArguments): string {
                $bodyArguments = $arguments;
                $recorder->events[] = 'body';

                return 'body-value';
            },
        );

        self::assertSame('body-value', $result);
        self::assertSame([], $bodyArguments);
        self::assertSame(1, $recorder->resetCount);
        self::assertSame(
            [
                'before',
                'body',
                'after',
                'reset',
            ],
            $recorder->events,
        );

        self::assertAfterRanBeforeReset($recorder);
    }

    public function testBodyThrowableRemainsPrimaryWhenResetAlsoFails(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();

        $bodyFailure = new \RuntimeException('body-failure');
        $resetFailure = new \RuntimeException(
            'unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
        );

        $runtime = self::runtime(
            recorder: $recorder,
            resetFailure: $resetFailure,
        );

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($recorder, $bodyFailure): never {
                    $recorder->events[] = 'body';

                    throw $bodyFailure;
                },
            );

            self::fail('Expected KernelRuntime to surface the body throwable.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($bodyFailure, $throwable);
        }

        self::assertSame(1, $recorder->resetCount);
        self::assertSame(
            [
                'before',
                'body',
                'after',
                'reset',
            ],
            $recorder->events,
        );
        self::assertSame(Outcome::FATAL_ERROR, $recorder->afterResults[0]['outcome']);
        self::assertAfterRanBeforeReset($recorder);
    }

    public function testBodyThrowableRemainsPrimaryWhenAfterHookAlsoThrows(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();

        $bodyFailure = new \RuntimeException('body-failure');
        $afterFailure = new \RuntimeException('after-hook-failure');

        $runtime = self::runtime(
            recorder: $recorder,
            afterFailure: $afterFailure,
        );

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($recorder, $bodyFailure): never {
                    $recorder->events[] = 'body';

                    throw $bodyFailure;
                },
            );

            self::fail('Expected KernelRuntime to keep the body throwable primary.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($bodyFailure, $throwable);
        }

        self::assertSame(1, $recorder->resetCount);
        self::assertSame(
            [
                'before',
                'body',
                'after',
                'reset',
            ],
            $recorder->events,
        );
        self::assertSame(Outcome::FATAL_ERROR, $recorder->afterResults[0]['outcome']);
        self::assertAfterRanBeforeReset($recorder);
    }

    public function testAfterHookThrowableIsSurfacedWhenBodySucceeds(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();

        $afterFailure = new \RuntimeException('after-hook-failure');

        $runtime = self::runtime(
            recorder: $recorder,
            afterFailure: $afterFailure,
        );

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($recorder): string {
                    $recorder->events[] = 'body';

                    return 'body-value';
                },
            );

            self::fail('Expected KernelRuntime to surface the after-hook throwable.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($afterFailure, $throwable);
        }

        self::assertSame(1, $recorder->resetCount);
        self::assertSame(
            [
                'before',
                'body',
                'after',
                'reset',
            ],
            $recorder->events,
        );
        self::assertAfterRanBeforeReset($recorder);
    }

    public function testResetFailureIsSurfacedWhenNoEarlierFailureExists(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();

        $resetFailure = new \RuntimeException(
            'unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
        );

        $runtime = self::runtime(
            recorder: $recorder,
            resetFailure: $resetFailure,
        );

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($recorder): string {
                    $recorder->events[] = 'body';

                    return 'body-value';
                },
            );

            self::fail('Expected KernelRuntime to surface reset failure.');
        } catch (KernelRuntimeException $exception) {
            self::assertSame(KernelRuntimeException::ERROR_CODE, $exception->errorCode());
            self::assertSame(KernelRuntimeException::REASON_RESET_FAILED, $exception->reason());
            self::assertSame(
                'CORETSIA_KERNEL_RUNTIME_ERROR: kernel-runtime-reset-failed',
                $exception->getMessage(),
            );

            self::assertSafeRuntimeExceptionMessage($exception);
        }

        self::assertSame(1, $recorder->resetCount);
        self::assertSame(
            [
                'before',
                'body',
                'after',
                'reset',
            ],
            $recorder->events,
        );
        self::assertAfterRanBeforeReset($recorder);
    }

    public function testBeforeHookThrowablePreventsBodyButRunsAfterPhaseAndReset(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();

        $beforeFailure = new \RuntimeException('before-hook-failure');

        $runtime = self::runtime(
            recorder: $recorder,
            beforeFailure: $beforeFailure,
        );

        $bodyWasCalled = false;

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use (&$bodyWasCalled): string {
                    $bodyWasCalled = true;

                    return 'body-value';
                },
            );

            self::fail('Expected KernelRuntime to surface the before-hook throwable.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($beforeFailure, $throwable);
        }

        self::assertFalse($bodyWasCalled);
        self::assertSame(1, $recorder->resetCount);
        self::assertCount(1, $recorder->afterResults);
        self::assertSame(Outcome::FATAL_ERROR, $recorder->afterResults[0]['outcome']);
        self::assertSame(
            [
                'before',
                'after',
                'reset',
            ],
            $recorder->events,
        );
        self::assertAfterRanBeforeReset($recorder);
    }

    public function testBeforeHookThrowableRemainsPrimaryWhenAfterHookAlsoThrows(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();

        $beforeFailure = new \RuntimeException('before-hook-failure');
        $afterFailure = new \RuntimeException('after-hook-failure');

        $runtime = self::runtime(
            recorder: $recorder,
            beforeFailure: $beforeFailure,
            afterFailure: $afterFailure,
        );

        $bodyWasCalled = false;

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use (&$bodyWasCalled): string {
                    $bodyWasCalled = true;

                    return 'body-value';
                },
            );

            self::fail('Expected KernelRuntime to keep the before-hook throwable primary.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($beforeFailure, $throwable);
        }

        self::assertFalse($bodyWasCalled);
        self::assertSame(1, $recorder->resetCount);
        self::assertCount(1, $recorder->afterResults);
        self::assertSame(Outcome::FATAL_ERROR, $recorder->afterResults[0]['outcome']);
        self::assertSame(
            [
                'before',
                'after',
                'reset',
            ],
            $recorder->events,
        );
        self::assertAfterRanBeforeReset($recorder);
    }

    public function testBeforeHookThrowableRemainsPrimaryWhenResetAlsoThrows(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();

        $beforeFailure = new \RuntimeException('before-hook-failure');
        $resetFailure = new \RuntimeException(
            'unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
        );

        $runtime = self::runtime(
            recorder: $recorder,
            beforeFailure: $beforeFailure,
            resetFailure: $resetFailure,
        );

        $bodyWasCalled = false;

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use (&$bodyWasCalled): string {
                    $bodyWasCalled = true;

                    return 'body-value';
                },
            );

            self::fail('Expected KernelRuntime to keep the before-hook throwable primary.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($beforeFailure, $throwable);
        }

        self::assertFalse($bodyWasCalled);
        self::assertSame(1, $recorder->resetCount);
        self::assertCount(1, $recorder->afterResults);
        self::assertSame(Outcome::FATAL_ERROR, $recorder->afterResults[0]['outcome']);
        self::assertSame(
            [
                'before',
                'after',
                'reset',
            ],
            $recorder->events,
        );
        self::assertAfterRanBeforeReset($recorder);
    }

    public function testLowLevelBeginResetsWhenBeforeHookThrowsAfterContextWrites(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();
        $contextStore = new ContextStore();

        $beforeFailure = new \RuntimeException('before-hook-failure');

        $runtime = self::runtime(
            recorder: $recorder,
            contextStore: $contextStore,
            beforeFailure: $beforeFailure,
        );

        try {
            $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);

            self::fail('Expected beginUnitOfWork() to surface the before-hook throwable.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($beforeFailure, $throwable);
        }

        self::assertSame(1, $recorder->resetCount);
        self::assertSame([], $recorder->afterResults);
        self::assertSame(
            [
                'before',
                'reset',
            ],
            $recorder->events,
        );

        self::assertFalse($contextStore->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_TYPE));
    }

    public function testAfterHookThrowableRemainsPrimaryWhenResetAlsoFails(): void
    {
        $recorder = new KernelRuntimeAlwaysResetsAfterUowRecorder();

        $afterFailure = new \RuntimeException('after-hook-failure');
        $resetFailure = new \RuntimeException(
            'unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
        );

        $runtime = self::runtime(
            recorder: $recorder,
            afterFailure: $afterFailure,
            resetFailure: $resetFailure,
        );

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($recorder): string {
                    $recorder->events[] = 'body';

                    return 'body-value';
                },
            );

            self::fail('Expected KernelRuntime to keep the after-hook throwable primary.');
        } catch (\RuntimeException $throwable) {
            self::assertSame($afterFailure, $throwable);
        }

        self::assertSame(1, $recorder->resetCount);
        self::assertSame(
            [
                'before',
                'body',
                'after',
                'reset',
            ],
            $recorder->events,
        );
        self::assertAfterRanBeforeReset($recorder);
    }

    private static function runtime(
        KernelRuntimeAlwaysResetsAfterUowRecorder $recorder,
        ?ContextStore $contextStore = null,
        ?\Throwable $beforeFailure = null,
        ?\Throwable $afterFailure = null,
        ?\Throwable $resetFailure = null,
    ): KernelRuntime {
        $contextStore ??= new ContextStore();

        $container = new KernelRuntimeAlwaysResetsAfterUowContainer([
            KernelRuntimeAlwaysResetsAfterUowBeforeHook::class => new KernelRuntimeAlwaysResetsAfterUowBeforeHook(
                recorder: $recorder,
                failure: $beforeFailure,
            ),
            KernelRuntimeAlwaysResetsAfterUowAfterHook::class => new KernelRuntimeAlwaysResetsAfterUowAfterHook(
                recorder: $recorder,
                failure: $afterFailure,
            ),
            KernelRuntimeAlwaysResetsAfterUowResetService::class => new KernelRuntimeAlwaysResetsAfterUowResetService(
                contextStore: $contextStore,
                recorder: $recorder,
                failure: $resetFailure,
            ),
        ]);

        $hookRegistry = new TagRegistry();

        $hookRegistry->add(
            KernelTags::KERNEL_HOOK_BEFORE_UOW,
            KernelRuntimeAlwaysResetsAfterUowBeforeHook::class,
        );

        $hookRegistry->add(
            KernelTags::KERNEL_HOOK_AFTER_UOW,
            KernelRuntimeAlwaysResetsAfterUowAfterHook::class,
        );

        $resetRegistry = new TagRegistry();

        $resetRegistry->add(
            FoundationTags::KERNEL_RESET,
            KernelRuntimeAlwaysResetsAfterUowResetService::class,
        );

        return new KernelRuntime(
            contextStore: $contextStore,
            resetOrchestrator: new ResetOrchestrator(
                container: $container,
                tagRegistry: $resetRegistry,
            ),
            stopwatch: new Stopwatch(),
            uowIds: new KernelRuntimeAlwaysResetsAfterUowIdGenerator(
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIdProvider: new KernelRuntimeAlwaysResetsAfterUowCorrelationIdProvider(
                '01B7X3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker(
                container: $container,
                tags: $hookRegistry,
            ),
            logger: new NullLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
        );
    }

    private static function assertAfterRanBeforeReset(KernelRuntimeAlwaysResetsAfterUowRecorder $recorder): void
    {
        self::assertLessThan(
            self::eventIndex($recorder->events, 'reset'),
            self::eventIndex($recorder->events, 'after'),
        );
    }

    /**
     * @param list<string> $events
     */
    private static function eventIndex(array $events, string $event): int
    {
        $index = \array_search($event, $events, true);

        self::assertIsInt($index);

        return $index;
    }

    private static function assertSafeRuntimeExceptionMessage(KernelRuntimeException $exception): void
    {
        self::assertStringNotContainsString('unsafe-token', $exception->getMessage());
        self::assertStringNotContainsString('Authorization', $exception->getMessage());
        self::assertStringNotContainsString('Cookie', $exception->getMessage());
        self::assertStringNotContainsString('session_id', $exception->getMessage());
        self::assertStringNotContainsString('SELECT * FROM users', $exception->getMessage());
        self::assertStringNotContainsString('/tmp/coretsia-secret', $exception->getMessage());
        self::assertStringNotContainsString(__DIR__, $exception->getMessage());
    }
}

final class KernelRuntimeAlwaysResetsAfterUowRecorder
{
    /**
     * @var list<string>
     */
    public array $events = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $afterResults = [];

    public int $resetCount = 0;
}

final readonly class KernelRuntimeAlwaysResetsAfterUowBeforeHook implements BeforeUowHookInterface
{
    public function __construct(
        private KernelRuntimeAlwaysResetsAfterUowRecorder $recorder,
        private ?\Throwable $failure = null,
    ) {
    }

    public function beforeUow(array $context): void
    {
        $this->recorder->events[] = 'before';

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}

final readonly class KernelRuntimeAlwaysResetsAfterUowAfterHook implements AfterUowHookInterface
{
    public function __construct(
        private KernelRuntimeAlwaysResetsAfterUowRecorder $recorder,
        private ?\Throwable $failure = null,
    ) {
    }

    public function afterUow(array $context, array $result): void
    {
        $this->recorder->events[] = 'after';
        $this->recorder->afterResults[] = $result;

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}

final readonly class KernelRuntimeAlwaysResetsAfterUowResetService implements ResetInterface
{
    public function __construct(
        private ContextStore $contextStore,
        private KernelRuntimeAlwaysResetsAfterUowRecorder $recorder,
        private ?\Throwable $failure = null,
    ) {
    }

    public function reset(): void
    {
        $this->recorder->events[] = 'reset';
        ++$this->recorder->resetCount;

        $this->contextStore->reset();

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}

final readonly class KernelRuntimeAlwaysResetsAfterUowIdGenerator implements IdGeneratorInterface
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

final readonly class KernelRuntimeAlwaysResetsAfterUowCorrelationIdProvider implements CorrelationIdProviderInterface
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

final readonly class KernelRuntimeAlwaysResetsAfterUowContainer implements ContainerInterface
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
