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
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;

final class CommandRunnerObservabilityFailureIsolationTest extends TestCase
{
    #[DataProvider('telemetryFailures')]
    public function testIndependentTelemetryFailuresCannotChangeExitOrRetryCommand(string $failure): void
    {
        $probe = new FailureIsolationProbe($failure);
        self::assertSame(
            17,
            self::runner($probe)->run(
                self::descriptor(),
                new ArgvInput(['owner:isolation'], 'owner:isolation', [], []),
                new CommandOutputBuffer(),
                'plain',
            ),
        );
        self::assertSame(1, $probe->commandCalls);
        self::assertSame(1, $probe->uowCalls);
        self::assertSame(1, $probe->cleanupCalls);
        self::assertFalse($probe->insideUow);
        self::assertSame(1, $probe->tracerStarts);
        self::assertSame(1, $probe->increments);
        self::assertSame(1, $probe->observations);
        self::assertSame(1, $probe->logCalls);
        if (\in_array($failure, ['context.has', 'context.get'], true)) {
            self::assertArrayNotHasKey('correlation_id', $probe->loggedContext);
            self::assertArrayNotHasKey('uow_id', $probe->loggedContext);
        }
        if ($failure === 'span.attributes') {
            self::assertSame(1, $probe->spanEnds, 'Span end still attempted after attribute failure.');
        }
        if ($failure === 'span.end') {
            self::assertSame(1, $probe->spanEnds);
        }
        if ($failure === 'tracer.start') {
            self::assertSame(0, $probe->spanEnds);
        }
        foreach ($probe->events as $event) {
            self::assertStringNotContainsString('kernel.uow', $event);
            self::assertStringNotContainsString('kernel.operation', $event);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function telemetryFailures(): iterable
    {
        yield 'tracer-start' => ['tracer.start'];
        yield 'span-attribute-update' => ['span.attributes'];
        yield 'span-end' => ['span.end'];
        yield 'meter-counter' => ['meter.increment'];
        yield 'meter-duration' => ['meter.observe'];
        yield 'logger' => ['logger.log'];
        yield 'context-presence' => ['context.has'];
        yield 'context-value' => ['context.get'];
    }

    #[DataProvider('telemetryFailures')]
    public function testOriginalCommandThrowableSurvivesEveryTelemetryFailure(string $failure): void
    {
        $probe = new FailureIsolationProbe($failure);
        $original = new \RuntimeException('PRIMARY_SECRET /private/runtime.env');
        $probe->commandThrowable = $original;
        try {
            self::runner($probe)->run(
                self::descriptor(),
                new ArgvInput(['owner:isolation'], 'owner:isolation', [], []),
                new CommandOutputBuffer(),
                'json',
            );
        } catch (\RuntimeException $caught) {
            self::assertSame($original, $caught);
            self::assertSame(1, $probe->commandCalls);
            self::assertSame(1, $probe->uowCalls);
            self::assertSame(1, $probe->cleanupCalls);
            self::assertSame(1, $probe->increments);
            self::assertSame(1, $probe->observations);
            self::assertSame(1, $probe->logCalls);
            self::assertFalse($probe->insideUow);
            self::assertStringNotContainsString('PRIMARY_SECRET', \implode(',', $probe->events));
            return;
        }
        self::fail('Original command Throwable must remain primary.');
    }

    public function testRealStopwatchReportsNonNegativeIntegerMilliseconds(): void
    {
        $probe = new FailureIsolationProbe('');
        self::assertSame(
            17,
            self::runner($probe)->run(
                self::descriptor(),
                new ArgvInput(['owner:isolation'], 'owner:isolation', [], []),
                new CommandOutputBuffer(),
                'table',
            ),
        );
        self::assertCount(1, $probe->durationValues);
        self::assertIsInt($probe->durationValues[0]);
        self::assertGreaterThanOrEqual(0, $probe->durationValues[0]);
        self::assertSame(['operation' => 'owner:isolation', 'outcome' => 'failure'], $probe->labels[0]);
    }

    #[DataProvider('unsafeContextValues')]
    public function testUnsafeContextIdentifiersAreOmittedFromTelemetry(mixed $value): void
    {
        $probe = new FailureIsolationProbe('');
        $probe->contextValue = $value;

        self::assertSame(
            17,
            self::runner($probe)->run(
                self::descriptor(),
                new ArgvInput(['owner:isolation'], 'owner:isolation', [], []),
                new CommandOutputBuffer(),
                'plain',
            ),
        );
        self::assertSame(1, $probe->commandCalls);
        self::assertSame(1, $probe->uowCalls);
        self::assertSame(1, $probe->cleanupCalls);
        self::assertSame([
            'operation' => 'owner:isolation',
            'outcome' => 'failure',
            'exit_code' => 17,
        ], $probe->loggedContext);
    }

    /** @return iterable<string, array{mixed}> */
    public static function unsafeContextValues(): iterable
    {
        yield 'absolute-path' => ['/private/credentials.txt'];
        yield 'spaces' => ['invalid context value'];
        yield 'control-character' => ["bad\nvalue"];
        yield 'too-long' => [\str_repeat('x', 129)];
        yield 'non-string' => [42];
    }

    public function testStopwatchIsolatedByThrowableGuardsWithoutReplacingFinalFoundationClass(): void
    {
        self::assertTrue(new \ReflectionClass(Stopwatch::class)->isFinal());
        $method = new \ReflectionMethod(CommandRunner::class, 'run');
        $path = $method->getFileName();
        self::assertIsString($path);
        $lines = \file($path);
        self::assertIsArray($lines);
        $source = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );

        self::assertSame(
            1,
            \preg_match_all(
                '/try\s*\{\s*\$start\s*=\s*\$this->stopwatch->start\(\);\s*}\s*catch\s*\(\\\\Throwable\)/',
                $source,
            ),
        );
        self::assertSame(
            1,
            \preg_match_all(
                '/try\s*\{\s*\$durationMs\s*=\s*\\\\max\(0,\s*\$this->stopwatch->stop\(\$start\)\);\s*}\s*catch\s*\(\\\\Throwable\)\s*\{\s*\$durationMs\s*=\s*0\s*;/',
                $source,
            ),
        );
        self::assertStringContainsString('if ($start !== null)', $source);
        self::assertStringNotContainsString('new Stopwatch()', $source);
    }

    private static function runner(FailureIsolationProbe $probe): CommandRunner
    {
        $runtime = new class($probe) implements KernelRuntimeInterface {
            public function __construct(private FailureIsolationProbe $probe)
            {
            }

            public function runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
            {
                ++$this->probe->uowCalls;
                $this->probe->insideUow = true;
                $this->probe->events[] = 'uow.enter';
                try {
                    return $body();
                } finally {
                    $this->probe->insideUow = false;
                    ++$this->probe->cleanupCalls;
                    $this->probe->events[] = 'uow.cleanup';
                }
            }

            public function beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
            {
                throw new \LogicException('No nested UoW.');
            }

            public function afterUnitOfWork(
                UnitOfWorkHandle $handle,
                string $outcome,
                ?\Throwable $error = null,
                array $extensions = [],
            ): array {
                throw new \LogicException('No low-level UoW.');
            }
        };
        $context = new class($probe) implements ContextAccessorInterface {
            public function __construct(private FailureIsolationProbe $probe)
            {
            }

            public function has(string $key): bool
            {
                self::guard($this->probe, 'context.has');
                return $this->probe->insideUow;
            }

            public function get(string $key): mixed
            {
                self::guard($this->probe, 'context.get');
                return $this->probe->contextValue;
            }

            private static function guard(FailureIsolationProbe $probe, string $step): void
            {
                if ($probe->failure === $step) {
                    throw new \RuntimeException('TELEMETRY_SECRET');
                }
            }
        };
        $span = new class($probe) implements SpanInterface {
            public function __construct(private FailureIsolationProbe $probe)
            {
            }

            public function name(): string
            {
                return 'cli.command';
            }

            public function setAttribute(string $key, mixed $value): void
            {
                throw new \LogicException('unexpected');
            }

            public function setAttributes(array $attributes): void
            {
                if ($this->probe->failure === 'span.attributes') {
                    throw new \RuntimeException('TELEMETRY_SECRET');
                }
            }

            public function addEvent(string $name, array $attributes = []): void
            {
                throw new \LogicException('unexpected');
            }

            public function recordException(\Throwable $throwable, array $attributes = []): void
            {
                throw new \LogicException('unexpected');
            }

            public function end(): void
            {
                ++$this->probe->spanEnds;
                if ($this->probe->failure === 'span.end') {
                    throw new \RuntimeException('TELEMETRY_SECRET');
                }
            }
        };
        $tracer = new class($probe, $span) implements TracerPortInterface {
            public function __construct(private FailureIsolationProbe $probe, private SpanInterface $span)
            {
            }

            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                ++$this->probe->tracerStarts;
                if ($this->probe->failure === 'tracer.start') {
                    throw new \RuntimeException('TELEMETRY_SECRET');
                }
                return $this->span;
            }

            public function inSpan(string $name, callable $callback, array $attributes = []): mixed
            {
                throw new \LogicException('unexpected');
            }

            public function currentSpan(): ?SpanInterface
            {
                return null;
            }
        };
        $meter = new class($probe) implements MeterPortInterface {
            public function __construct(private FailureIsolationProbe $probe)
            {
            }

            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
                ++$this->probe->increments;
                if ($this->probe->failure === 'meter.increment') {
                    throw new \RuntimeException('TELEMETRY_SECRET');
                }
            }

            public function observe(string $name, int $value, array $labels = []): void
            {
                ++$this->probe->observations;
                $this->probe->durationValues[] = $value;
                $this->probe->labels[] = $labels;
                if ($this->probe->failure === 'meter.observe') {
                    throw new \RuntimeException('TELEMETRY_SECRET');
                }
            }
        };
        $logger = new class($probe) extends AbstractLogger {
            public function __construct(private FailureIsolationProbe $probe)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                ++$this->probe->logCalls;
                $this->probe->loggedContext = $context;
                if ($this->probe->failure === 'logger.log') {
                    throw new \RuntimeException('TELEMETRY_SECRET');
                }
            }
        };
        $container = new class($probe) implements ContainerInterface {
            public function __construct(private FailureIsolationProbe $probe)
            {
            }

            public function has(string $id): bool
            {
                throw new \LogicException('enumeration forbidden');
            }

            public function get(string $id): mixed
            {
                if ($id !== FailureIsolationCommand::class || !$this->probe->insideUow) {
                    throw new \LogicException('resolution must occur inside UoW');
                }
                return new FailureIsolationCommand($this->probe);
            }
        };
        return new CommandRunner(
            $runtime,
            $context,
            $tracer,
            $meter,
            $logger,
            $container,
            new Stopwatch(),
        );
    }

    private static function descriptor(): CommandDescriptor
    {
        return CommandDescriptor::fromValidatedMetadata(FailureIsolationCommand::class, [
            'name' => FailureIsolationCommand::NAME,
            'summary' => FailureIsolationCommand::SUMMARY,
            'group' => FailureIsolationCommand::GROUP,
            'hidden' => FailureIsolationCommand::HIDDEN,
            'arguments' => FailureIsolationCommand::ARGUMENTS,
            'options' => FailureIsolationCommand::OPTIONS,
        ]);
    }
}

final class FailureIsolationProbe
{
    public int $commandCalls = 0;
    public int $uowCalls = 0;
    public int $cleanupCalls = 0;
    public int $tracerStarts = 0;
    public int $spanEnds = 0;
    public int $increments = 0;
    public int $observations = 0;
    public int $logCalls = 0;
    /** @var array<string, mixed> */
    public array $loggedContext = [];
    public bool $insideUow = false;
    public ?\Throwable $commandThrowable = null;
    public mixed $contextValue = 'safe-context-id';
    /** @var list<string> */
    public array $events = [];
    /** @var list<int> */
    public array $durationValues = [];
    /** @var list<array<string, string|int|bool>> */
    public array $labels = [];

    public function __construct(public string $failure)
    {
    }
}

final class FailureIsolationCommand implements CommandInterface
{
    public const string NAME = 'owner:isolation';
    public const string SUMMARY = 'Isolate telemetry failures.';
    public const string GROUP = 'owner';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct(private FailureIsolationProbe $probe)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        ++$this->probe->commandCalls;
        if ($this->probe->commandThrowable !== null) {
            throw $this->probe->commandThrowable;
        }
        return 17;
    }
}
