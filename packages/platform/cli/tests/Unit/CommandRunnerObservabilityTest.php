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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;

final class CommandRunnerObservabilityTest extends TestCase
{
    public function testSuccessfulCommandEmitsOneBoundedTelemetrySetInLifecycleOrder(): void
    {
        [$runner, $events, $tracer, $meter, $logger, $runtime, $command] = self::fixture(0);
        $input = new ArgvInput(
            ['owner:observe', '--secret=RAW_ARG'],
            'owner:observe',
            [],
            ['secret' => 'RAW_ARG']
        );
        $output = new CommandOutputBuffer();
        $output->text('OUTPUT_SECRET');
        self::assertSame(0, $runner->run(self::descriptor(), $input, $output, 'table'));

        self::assertSame(1, $command->calls);
        self::assertSame($input, $command->input);
        self::assertSame($output, $command->output);
        self::assertSame(1, $runtime->calls);
        self::assertSame(UnitOfWorkType::CLI, $runtime->type);
        self::assertSame(['operation' => 'owner:observe', 'output_format' => 'table'], $runtime->attributes);
        self::assertSame([
            'span.start',
            'uow.enter',
            'command.run',
            'uow.cleanup',
            'span.attributes',
            'span.end',
            'meter.increment',
            'meter.observe',
            'log',
        ], $events->items);

        self::assertSame(
            [['cli.command', ['operation' => 'owner:observe', 'format' => 'table']]],
            $tracer->started
        );
        self::assertCount(1, $tracer->span->updates);
        self::assertSame([
            'operation' => 'owner:observe',
            'format' => 'table',
            'outcome' => 'success',
            'exit_code' => 0,
        ], $tracer->span->updates[0]);
        self::assertSame(1, $tracer->span->ends);
        self::assertSame([
            [
                'cli.command_total',
                1,
                ['operation' => 'owner:observe', 'outcome' => 'success'],
            ],
        ], $meter->increments);
        self::assertCount(1, $meter->observations);
        self::assertSame('cli.command_duration_ms', $meter->observations[0][0]);
        self::assertIsInt($meter->observations[0][1]);
        self::assertGreaterThanOrEqual(0, $meter->observations[0][1]);
        self::assertSame(['operation' => 'owner:observe', 'outcome' => 'success'], $meter->observations[0][2]);
        self::assertSame([
            [
                'info',
                'cli.command',
                [
                    'operation' => 'owner:observe',
                    'outcome' => 'success',
                    'exit_code' => 0,
                    'correlation_id' => 'corr-123',
                    'uow_id' => 'uow-456',
                ],
            ],
        ], $logger->entries);
        self::assertSame(['correlation_id', 'uow_id'], $runtime->contextKeysSeen);

        $export = \var_export([
            $tracer->started,
            $tracer->span->updates,
            $meter->increments,
            $meter->observations,
            $logger->entries,
        ], true);
        foreach (
            [
                'RAW_ARG',
                '--secret',
                'OUTPUT_SECRET',
                'password',
                '/private/',
                'kernel.uow',
                'kernel.operation',
                'output_format',
            ] as $needle
        ) {
            self::assertStringNotContainsString($needle, $export);
        }
    }

    public function testNonzeroReturnPreservesExactExitCodeAndFailureOutcome(): void
    {
        foreach ([1, 2, 3, 255] as $expected) {
            [$runner, $events, $tracer, $meter, $logger, , $command] = self::fixture($expected);
            self::assertSame(
                $expected,
                $runner->run(
                    self::descriptor(),
                    new ArgvInput(['owner:observe'], 'owner:observe', [], []),
                    new CommandOutputBuffer(),
                    'json',
                ),
            );
            self::assertSame(1, $command->calls);
            self::assertSame('failure', $tracer->span->updates[0]['outcome']);
            self::assertSame($expected, $tracer->span->updates[0]['exit_code']);
            self::assertSame(['operation' => 'owner:observe', 'outcome' => 'failure'], $meter->increments[0][2]);
            self::assertSame('error', $logger->entries[0][0]);
            self::assertSame($expected, $logger->entries[0][2]['exit_code']);
        }
    }

    public function testThrowingCommandEmitsFailureMetadataButRetainsOriginalThrowable(): void
    {
        [$runner, , $tracer, $meter, $logger, , $command] = self::fixture(0);
        $original = new \RuntimeException('PRIVATE_PAYLOAD /private/secrets.env');
        $command->failure = $original;
        try {
            $runner->run(
                self::descriptor(),
                new ArgvInput(['owner:observe'], 'owner:observe', [], []),
                new CommandOutputBuffer(),
                'plain',
            );
        } catch (\RuntimeException $caught) {
            self::assertSame($original, $caught);
            self::assertSame(1, $command->calls);
            self::assertSame('failure', $tracer->span->updates[0]['outcome']);
            self::assertSame(1, $tracer->span->updates[0]['exit_code']);
            self::assertSame(['operation' => 'owner:observe', 'outcome' => 'failure'], $meter->increments[0][2]);
            self::assertSame(1, $logger->entries[0][2]['exit_code']);
            $export = \var_export([
                $tracer->started,
                $tracer->span->updates,
                $meter->increments,
                $meter->observations,
                $logger->entries,
            ], true);
            self::assertStringNotContainsString('PRIVATE_PAYLOAD', $export);
            self::assertStringNotContainsString('/private/secrets.env', $export);
            return;
        }
        self::fail('Original command failure must propagate.');
    }

    /** @return array{CommandRunner, ObservabilityEvents, ObservabilityTracer, ObservabilityMeter, ObservabilityLogger, ObservabilityRuntime, ObservabilityCommand} */
    private static function fixture(int $exit): array
    {
        $events = new ObservabilityEvents();
        $runtime = new ObservabilityRuntime($events);
        $tracer = new ObservabilityTracer($events);
        $meter = new ObservabilityMeter($events);
        $logger = new ObservabilityLogger($events);
        $command = new ObservabilityCommand($events, $exit);
        $container = new class($command) implements ContainerInterface {
            public function __construct(private ObservabilityCommand $command)
            {
            }

            public function get(string $id): mixed
            {
                if ($id !== ObservabilityCommand::class) {
                    throw new \LogicException('unselected-command');
                }
                return $this->command;
            }

            public function has(string $id): bool
            {
                throw new \LogicException('unexpected enumeration');
            }
        };
        $context = new class($runtime) implements ContextAccessorInterface {
            public function __construct(private ObservabilityRuntime $runtime)
            {
            }

            public function has(string $key): bool
            {
                if (!$this->runtime->inside) {
                    throw new \LogicException('context read after reset');
                }
                $this->runtime->contextKeysSeen[] = $key;
                return \in_array($key, [ContextKeys::CORRELATION_ID, ContextKeys::UOW_ID], true);
            }

            public function get(string $key): mixed
            {
                if (!$this->runtime->inside) {
                    throw new \LogicException('context read after reset');
                }
                return $key === ContextKeys::CORRELATION_ID ? 'corr-123' : 'uow-456';
            }
        };
        return [
            new CommandRunner(
                $runtime,
                $context,
                $tracer,
                $meter,
                $logger,
                $container,
                new Stopwatch(),
            ),
            $events,
            $tracer,
            $meter,
            $logger,
            $runtime,
            $command,
        ];
    }

    private static function descriptor(): CommandDescriptor
    {
        return CommandDescriptor::fromValidatedMetadata(ObservabilityCommand::class, [
            'name' => ObservabilityCommand::NAME,
            'summary' => ObservabilityCommand::SUMMARY,
            'group' => ObservabilityCommand::GROUP,
            'hidden' => ObservabilityCommand::HIDDEN,
            'arguments' => ObservabilityCommand::ARGUMENTS,
            'options' => ObservabilityCommand::OPTIONS,
        ]);
    }
}

final class ObservabilityEvents
{
    /** @var list<string> */
    public array $items = [];
}

final class ObservabilityCommand implements CommandInterface
{
    public const string NAME = 'owner:observe';
    public const string SUMMARY = 'Observe a command.';
    public const string GROUP = 'owner';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];
    public int $calls = 0;
    public ?InputInterface $input = null;
    public ?OutputInterface $output = null;
    public ?\Throwable $failure = null;

    public function __construct(private ObservabilityEvents $events, private int $exit)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->events->items[] = 'command.run';
        ++$this->calls;
        $this->input = $input;
        $this->output = $output;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        return $this->exit;
    }
}

final class ObservabilityRuntime implements KernelRuntimeInterface
{
    public bool $inside = false;
    public int $calls = 0;
    public ?string $type = null;
    /** @var array<string, mixed> */
    public array $attributes = [];
    /** @var list<string> */
    public array $contextKeysSeen = [];

    public function __construct(private ObservabilityEvents $events)
    {
    }

    public function runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
    {
        ++$this->calls;
        $this->type = $type;
        $this->attributes = $attributes;
        $this->inside = true;
        $this->events->items[] = 'uow.enter';
        try {
            return $body();
        } finally {
            $this->inside = false;
            $this->events->items[] = 'uow.cleanup';
        }
    }

    public function beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
    {
        throw new \LogicException('unexpected nested UoW');
    }

    public function afterUnitOfWork(
        UnitOfWorkHandle $handle,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array {
        throw new \LogicException('unexpected low-level UoW');
    }
}

final class ObservabilityTracer implements TracerPortInterface
{
    /** @var list<array{string, array<string, mixed>}> */
    public array $started = [];
    public ObservabilitySpan $span;

    public function __construct(private ObservabilityEvents $events)
    {
        $this->span = new ObservabilitySpan($events);
    }

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $this->events->items[] = 'span.start';
        $this->started[] = [$name, $attributes];
        return $this->span;
    }

    public function inSpan(string $name, callable $callback, array $attributes = []): mixed
    {
        throw new \LogicException('runner must own span completion');
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}

final class ObservabilitySpan implements SpanInterface
{
    /** @var list<array<string, mixed>> */
    public array $updates = [];
    public int $ends = 0;

    public function __construct(private ObservabilityEvents $events)
    {
    }

    public function name(): string
    {
        return 'cli.command';
    }

    public function setAttribute(string $key, mixed $value): void
    {
        throw new \LogicException('Only one final attribute update permitted.');
    }

    public function setAttributes(array $attributes): void
    {
        $this->events->items[] = 'span.attributes';
        $this->updates[] = $attributes;
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        throw new \LogicException('Not required for CLI span.');
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
        throw new \LogicException('Raw throwable telemetry forbidden.');
    }

    public function end(): void
    {
        $this->events->items[] = 'span.end';
        ++$this->ends;
    }
}

final class ObservabilityMeter implements MeterPortInterface
{
    /** @var list<array{string, int, array<string, string|int|bool>}> */
    public array $increments = [];
    /** @var list<array{string, int, array<string, string|int|bool>}> */
    public array $observations = [];

    public function __construct(private ObservabilityEvents $events)
    {
    }

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->events->items[] = 'meter.increment';
        $this->increments[] = [$name, $delta, $labels];
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->events->items[] = 'meter.observe';
        $this->observations[] = [$name, $value, $labels];
    }
}

final class ObservabilityLogger extends AbstractLogger
{
    /** @var list<array{string, string, array<string, mixed>}> */
    public array $entries = [];

    public function __construct(private ObservabilityEvents $events)
    {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->events->items[] = 'log';
        $this->entries[] = [(string) $level, (string) $message, $context];
    }
}
