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
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowExecutionProbeCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowIntegrationFixture;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class WorkflowRunUsesOneKernelUowPerStepTest extends TestCase
{
    protected function setUp(): void
    {
        WorkflowExecutionProbeCommand::reset();
    }

    public function testRealKernelUowCompletesAfterHookAndResetForEveryStepAcrossRepeatedInvocations(): void
    {
        [$runtime, $store, $events] = self::kernelRuntime();
        $fixture = self::fixture($runtime, $store, ['one' => 'mixed', 'two' => 'mixed']);
        WorkflowExecutionProbeCommand::$context = $store;

        $first = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(0, $first['exit'], $first['stderr']);
        self::assertSame([], $store->all());
        $second = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(0, $second['exit'], $second['stderr']);
        self::assertSame([], $store->all());
        self::assertSame($first['stdout'], $second['stdout']);

        self::assertCount(4, WorkflowExecutionProbeCommand::$calls);
        self::assertSame(['one', 'two', 'one', 'two'], \array_column(WorkflowExecutionProbeCommand::$calls, 'subject'));
        self::assertSame([null, null, null, null], \array_column(WorkflowExecutionProbeCommand::$calls, 'previous'));
        self::assertSame(
            ['uow-1', 'uow-2', 'uow-3', 'uow-4'],
            \array_column(WorkflowExecutionProbeCommand::$calls, 'uow')
        );
        self::assertSame([
            'before:uow-1',
            'after:uow-1',
            'reset:uow-1',
            'before:uow-2',
            'after:uow-2',
            'reset:uow-2',
            'before:uow-3',
            'after:uow-3',
            'reset:uow-3',
            'before:uow-4',
            'after:uow-4',
            'reset:uow-4',
        ], $events->events);
        self::assertCount(
            4,
            \array_unique(
                \array_map(
                    static fn (array $call): int => \spl_object_id($call['output']),
                    WorkflowExecutionProbeCommand::$calls
                ),
            ),
        );
        self::assertNotSame(
            WorkflowExecutionProbeCommand::$calls[0]['input'],
            WorkflowExecutionProbeCommand::$calls[2]['input'],
        );
        self::assertNotSame(
            WorkflowExecutionProbeCommand::$calls[0]['output'],
            WorkflowExecutionProbeCommand::$calls[2]['output'],
        );
    }

    public function testThrowableAndNonZeroExitStillResetBeforeNextInvocation(): void
    {
        [$runtime, $store, $events] = self::kernelRuntime();
        $fixture = new WorkflowIntegrationFixture([
            'throwing' => [
                'summary' => 'Throw within a Kernel CLI UoW.',
                'steps' => [
                    [
                        'command' => WorkflowExecutionProbeCommand::NAME,
                        'arguments' => ['throw'],
                        'options' => ['mode' => 'throw'],
                    ],
                ],
            ],
            'failing' => [
                'summary' => 'Return a non-zero command exit code.',
                'steps' => [
                    [
                        'command' => WorkflowExecutionProbeCommand::NAME,
                        'arguments' => ['failed'],
                        'options' => ['mode' => 'fail'],
                    ],
                    [
                        'command' => WorkflowExecutionProbeCommand::NAME,
                        'arguments' => ['never'],
                        'options' => [],
                    ],
                ],
            ],
            'recovery' => [
                'summary' => 'Execute after both failure types.',
                'steps' => [
                    [
                        'command' => WorkflowExecutionProbeCommand::NAME,
                        'arguments' => ['recovered'],
                        'options' => [],
                    ],
                ],
            ],
        ], kernelRuntime: $runtime, context: $store, withProbe: true);
        WorkflowExecutionProbeCommand::$context = $store;

        $failure = $fixture->run(['workflow:run', 'throwing', '--format=json']);
        self::assertSame(1, $failure['exit']);
        self::assertSame([], $store->all());
        self::assertSame(['before:uow-1', 'after:uow-1', 'reset:uow-1'], $events->events);
        self::assertStringNotContainsString('PRIVATE_THROW_throw', $failure['stdout']);
        self::assertSame(['throw'], \array_column(WorkflowExecutionProbeCommand::$calls, 'subject'));

        WorkflowExecutionProbeCommand::reset();
        WorkflowExecutionProbeCommand::$context = $store;
        $nonzero = $fixture->run(['workflow:run', 'failing', '--format=json']);
        self::assertSame(17, $nonzero['exit']);
        self::assertSame(['failed'], \array_column(WorkflowExecutionProbeCommand::$calls, 'subject'));
        self::assertSame([], $store->all());
        self::assertSame('uow-2', WorkflowExecutionProbeCommand::$calls[0]['uow']);
        self::assertNull(WorkflowExecutionProbeCommand::$calls[0]['previous']);
        self::assertSame(
            [0],
            \array_column(
                WorkflowIntegrationFixture::document($nonzero)['data']['records'][0]['payload']['steps'],
                'index',
            )
        );

        WorkflowExecutionProbeCommand::reset();
        WorkflowExecutionProbeCommand::$context = $store;
        $recovery = $fixture->run(['workflow:run', 'recovery', '--format=json']);
        self::assertSame(0, $recovery['exit'], $recovery['stderr']);
        self::assertSame(['recovered'], \array_column(WorkflowExecutionProbeCommand::$calls, 'subject'));
        self::assertSame('uow-3', WorkflowExecutionProbeCommand::$calls[0]['uow']);
        self::assertNull(WorkflowExecutionProbeCommand::$calls[0]['previous']);
        self::assertSame([], $store->all());
        self::assertSame(
            'recovery',
            WorkflowIntegrationFixture::document($recovery)['data']['records'][0]['payload']['workflow'],
        );
        self::assertSame([
            'before:uow-1',
            'after:uow-1',
            'reset:uow-1',
            'before:uow-2',
            'after:uow-2',
            'reset:uow-2',
            'before:uow-3',
            'after:uow-3',
            'reset:uow-3',
        ], $events->events);
    }

    public function testKernelItselfStillRejectsActualNestedUowThroughLifecycleGate(): void
    {
        [$runtime, $store, $events] = self::kernelRuntime();
        $ran = false;
        $runtime->runUnitOfWork(UnitOfWorkType::CLI, static function () use ($runtime, &$ran): void {
            try {
                $runtime->runUnitOfWork(UnitOfWorkType::CLI, static function () use (&$ran): void {
                    $ran = true;
                });
                self::fail('Kernel must reject nested UnitOfWork.');
            } catch (KernelRuntimeException $exception) {
                self::assertSame(KernelRuntimeException::REASON_UOW_ALREADY_ACTIVE, $exception->reason());
            }
        });
        self::assertFalse($ran);
        self::assertSame([], $store->all());
        self::assertSame(['before:uow-1', 'after:uow-1', 'reset:uow-1'], $events->events);
    }

    /** @param array<string,string> $modes */
    private static function fixture(
        KernelRuntime $runtime,
        ContextStore $store,
        array $modes,
    ): WorkflowIntegrationFixture {
        $steps = [];
        foreach ($modes as $subject => $mode) {
            $steps[] = [
                'command' => WorkflowExecutionProbeCommand::NAME,
                'arguments' => [$subject],
                'options' => ['mode' => $mode],
            ];
        }
        return new WorkflowIntegrationFixture(
            ['verify' => ['summary' => 'Kernel lifecycle integration.', 'steps' => $steps]],
            kernelRuntime: $runtime,
            context: $store,
            withProbe: true,
        );
    }

    /** @return array{KernelRuntime,ContextStore,WorkflowKernelUowEvents} */
    private static function kernelRuntime(): array
    {
        $store = new ContextStore();
        $events = new WorkflowKernelUowEvents();
        $services = new WorkflowKernelUowServices([
            WorkflowKernelUowBeforeHook::class => new WorkflowKernelUowBeforeHook($events),
            WorkflowKernelUowAfterHook::class => new WorkflowKernelUowAfterHook($events),
            WorkflowKernelUowReset::class => new WorkflowKernelUowReset($events, $store),
        ]);
        $hooks = new TagRegistry();
        $hooks->add(ReservedTags::KERNEL_HOOK_BEFORE_UOW, WorkflowKernelUowBeforeHook::class);
        $hooks->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, WorkflowKernelUowAfterHook::class);
        $resets = new TagRegistry();
        $resets->add(ReservedTags::KERNEL_RESET, WorkflowKernelUowReset::class);
        $runtime = new KernelRuntime(
            contextStore: $store,
            resetOrchestrator: new ResetOrchestrator($services, $resets),
            stopwatch: new Stopwatch(),
            uowIds: new WorkflowKernelUowIds(),
            correlationIdProvider: new WorkflowKernelUowCorrelation(),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker($services, $hooks),
            logger: new NullLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );
        return [$runtime, $store, $events];
    }
}

final class WorkflowKernelUowEvents
{
    /** @var list<string> */
    public array $events = [];
}

final readonly class WorkflowKernelUowBeforeHook implements BeforeUowHookInterface
{
    public function __construct(private WorkflowKernelUowEvents $events)
    {
    }

    public function beforeUow(array $context): void
    {
        $this->events->events[] = 'before:' . $context['uowId'];
    }
}

final readonly class WorkflowKernelUowAfterHook implements AfterUowHookInterface
{
    public function __construct(private WorkflowKernelUowEvents $events)
    {
    }

    public function afterUow(array $context, array $result): void
    {
        $this->events->events[] = 'after:' . $context['uowId'];
    }
}

final readonly class WorkflowKernelUowReset implements ResetInterface
{
    public function __construct(private WorkflowKernelUowEvents $events, private ContextStore $store)
    {
    }

    public function reset(): void
    {
        $this->events->events[] = 'reset:' . $this->store->get(ContextKeys::UOW_ID);
        $this->store->reset();
    }
}

final class WorkflowKernelUowIds implements IdGeneratorInterface
{
    private int $index = 0;

    public function generate(): string
    {
        return 'uow-' . ++$this->index;
    }
}

final readonly class WorkflowKernelUowCorrelation implements CorrelationIdProviderInterface
{
    public function correlationId(): ?string
    {
        return 'correlation-1';
    }
}

final readonly class WorkflowKernelUowServices implements ContainerInterface
{
    /** @param array<string,object> $services */
    public function __construct(private array $services)
    {
    }

    public function get(string $id): mixed
    {
        return $this->services[$id] ?? throw new \LogicException('workflow-kernel-hook-service-missing');
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
