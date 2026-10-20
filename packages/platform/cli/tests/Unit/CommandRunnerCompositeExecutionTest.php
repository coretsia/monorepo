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
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\CliCommandFailedException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use Coretsia\Platform\Cli\Runner\CompositeCommandInterface;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use Coretsia\Platform\Cli\Workflow\WorkflowStepInputFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;

final class CommandRunnerCompositeExecutionTest extends TestCase
{
    public function testRealWorkflowUsesOneKernelUowPerStepAndNoOuterUow(): void
    {
        $probe = new CompositeExecutionProbe();
        $runner = self::runner($probe);
        $catalog = self::catalog();
        $definitions = new WorkflowSchema()->normalize([
            'verify' => [
                'summary' => 'Execute one normal workflow step.',
                'steps' => [
                    ['command' => CompositeExecutionNormalCommand::NAME, 'arguments' => [], 'options' => []],
                ],
            ],
        ]);
        $workflow = new WorkflowRunCommand(
            new WorkflowCatalog($definitions, $catalog),
            new WorkflowRunner($catalog, new CommandInputValidator(), $runner, new WorkflowStepInputFactory()),
        );
        $probe->services = [
            WorkflowRunCommand::class => $workflow,
            CompositeExecutionNormalCommand::class => new CompositeExecutionNormalCommand($probe),
        ];
        $input = new ArgvInput(
            [WorkflowRunCommand::NAME, 'verify'],
            WorkflowRunCommand::NAME,
            ['verify'],
            [],
        );
        $output = new CommandOutputBuffer();

        self::assertSame(0, $runner->run($catalog->require(WorkflowRunCommand::NAME), $input, $output, 'json'));
        self::assertSame([WorkflowRunCommand::class, CompositeExecutionNormalCommand::class], $probe->resolved);
        self::assertSame([UnitOfWorkType::CLI], $probe->uowTypes);
        self::assertSame(1, $probe->normalCalls);
        self::assertSame(2, $probe->contextCalls, 'Only the normal child UoW may inspect context identifiers.');
        self::assertSame(2, \count($probe->spanStarts));
        self::assertSame(['cli.command', 'cli.command'], \array_column($probe->spanStarts, 'name'));
        self::assertSame(2, \count($probe->spanFinals));
        self::assertSame(2, \count($probe->counterCalls));
        self::assertSame(2, \count($probe->durationCalls));
        self::assertSame(2, \count($probe->logs));
        self::assertSame(2, $probe->spanEnds);
        self::assertSame([
            CompositeExecutionNormalCommand::NAME,
            WorkflowRunCommand::NAME,
        ], \array_column(\array_column($probe->counterCalls, 'labels'), 'operation'));
        self::assertSame(['cli.command_total', 'cli.command_total'], \array_column($probe->counterCalls, 'name'));
        self::assertSame(
            ['cli.command_duration_ms', 'cli.command_duration_ms'],
            \array_column($probe->durationCalls, 'name')
        );
        self::assertSame([
            'exit_code' => 0,
            'outcome' => 'success',
            'steps' => [
                [
                    'command' => CompositeExecutionNormalCommand::NAME,
                    'exit_code' => 0,
                    'index' => 0,
                    'records' => [['text' => 'one normal step', 'type' => 'text']],
                ],
            ],
            'workflow' => 'verify',
        ], $output->finalize()->records()[0]['payload']);

        self::assertSame([
            'operation' => WorkflowRunCommand::NAME,
            'format' => 'json',
        ], $probe->spanStarts[0]['attributes']);
        self::assertSame([
            'operation' => WorkflowRunCommand::NAME,
            'format' => 'json',
            'outcome' => 'success',
            'exit_code' => 0,
        ], $probe->spanFinals[1]['attributes']);
        self::assertSame(
            ['operation' => WorkflowRunCommand::NAME, 'outcome' => 'success'],
            $probe->counterCalls[1]['labels']
        );
        self::assertSame(
            ['operation' => WorkflowRunCommand::NAME, 'outcome' => 'success'],
            $probe->durationCalls[1]['labels']
        );
        self::assertSame([
            'operation' => WorkflowRunCommand::NAME,
            'outcome' => 'success',
            'exit_code' => 0,
        ], $probe->logs[1]['context']);
        self::assertSame(
            2,
            $probe->contextCalls,
            'The composite invocation must not add context reads after the child step.',
        );
    }

    public function testStandaloneNormalCommandKeepsOneCanonicalKernelUow(): void
    {
        $probe = new CompositeExecutionProbe();
        $probe->services = [CompositeExecutionNormalCommand::class => new CompositeExecutionNormalCommand($probe)];
        $runner = self::runner($probe);
        $input = new ArgvInput(
            [CompositeExecutionNormalCommand::NAME],
            CompositeExecutionNormalCommand::NAME,
            [],
            [],
        );
        $output = new CommandOutputBuffer();

        self::assertSame(
            0,
            $runner->run(
                self::catalog()->require(CompositeExecutionNormalCommand::NAME),
                $input,
                $output,
                'plain',
            ),
        );
        self::assertSame([UnitOfWorkType::CLI], $probe->uowTypes);
        self::assertSame([CompositeExecutionNormalCommand::class], $probe->resolved);
        self::assertSame(1, $probe->normalCalls);
        self::assertSame([['text' => 'one normal step', 'type' => 'text']], $output->finalize()->records());
        self::assertSame(1, \count($probe->counterCalls));
        self::assertSame(1, \count($probe->durationCalls));
    }

    public function testCompositeResolutionIsLazyAndIdentityFailuresDoNotStartKernelUow(): void
    {
        foreach (
            [
                'not-composite' => new \stdClass(),
                'both-interfaces' => new CompositeExecutionBothInterfacesCommand(),
                'wrong-exact-class' => new CompositeExecutionAdversarialCommand(),
            ] as $case => $service
        ) {
            $probe = new CompositeExecutionProbe();
            $probe->services = [WorkflowRunCommand::class => $service];
            $runner = self::runner($probe);
            $descriptor = self::catalog()->require(WorkflowRunCommand::NAME);
            self::assertSame([], $probe->resolved, $case);

            try {
                $runner->run($descriptor, self::input(), new CommandOutputBuffer(), 'table');
                self::fail('An invalid composite service must be rejected: ' . $case);
            } catch (InvalidCommandTagMetaException $exception) {
                self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
                self::assertSame(InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH, $exception->reason());
                self::assertSame(
                    InvalidCommandTagMetaException::ERROR_CODE . ': ' . InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH,
                    $exception->getMessage(),
                );
            }
            self::assertSame([WorkflowRunCommand::class], $probe->resolved);
            self::assertSame([], $probe->uowTypes);
            self::assertSame(0, $probe->contextCalls);
            self::assertSame(1, \count($probe->counterCalls));
            self::assertSame('cli.command', $probe->spanStarts[0]['name']);
            self::assertSame([
                'operation' => WorkflowRunCommand::NAME,
                'format' => 'table',
            ], $probe->spanStarts[0]['attributes']);
            self::assertSame([
                'operation' => WorkflowRunCommand::NAME,
                'format' => 'table',
                'outcome' => 'failure',
                'exit_code' => 1,
            ], $probe->spanFinals[0]['attributes']);
            self::assertSame(
                ['operation' => WorkflowRunCommand::NAME, 'outcome' => 'failure'],
                $probe->counterCalls[0]['labels']
            );
            self::assertSame(
                ['operation' => WorkflowRunCommand::NAME, 'outcome' => 'failure'],
                $probe->durationCalls[0]['labels']
            );
            self::assertSame([
                'operation' => WorkflowRunCommand::NAME,
                'outcome' => 'failure',
                'exit_code' => 1,
            ], $probe->logs[0]['context']);
        }
    }

    #[DataProvider('telemetryFailurePoints')]
    public function testCompositeIdentityFailureRetainsExceptionPrecedenceWhenTelemetryFails(string $failure): void
    {
        $probe = new CompositeExecutionProbe();
        $probe->failure = $failure;
        $probe->services = [WorkflowRunCommand::class => new \stdClass()];

        try {
            self::runner($probe)->run(
                self::catalog()->require(WorkflowRunCommand::NAME),
                self::input(),
                new CommandOutputBuffer(),
                'plain',
            );
            self::fail('Invalid composite service must not be accepted.');
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH, $exception->reason());
            self::assertSame(
                InvalidCommandTagMetaException::ERROR_CODE . ': ' . InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH,
                $exception->getMessage(),
            );
        }
        self::assertSame([], $probe->uowTypes);
        self::assertSame([WorkflowRunCommand::class], $probe->resolved);
        self::assertSame(1, \count($probe->counterCalls));
        self::assertSame(1, \count($probe->durationCalls));
    }

    public function testMatchingCompositeServiceImplementingBothInterfacesIsRejected(): void
    {
        $probe = new CompositeExecutionProbe();
        $probe->services = [
            CompositeExecutionBothInterfacesCommand::class => new CompositeExecutionBothInterfacesCommand(),
        ];

        try {
            self::runner($probe)->run(
                self::adversarialDescriptor(CompositeExecutionBothInterfacesCommand::class),
                self::input(),
                new CommandOutputBuffer(),
                'plain',
            );
            self::fail('A composite command must not also implement CommandInterface.');
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
            self::assertSame(InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH, $exception->reason());
        }

        self::assertSame([CompositeExecutionBothInterfacesCommand::class], $probe->resolved);
        self::assertSame([], $probe->uowTypes);
        self::assertSame(0, $probe->contextCalls);
    }

    public function testDefensiveNameMismatchIsRejectedWithoutKernelUow(): void
    {
        $probe = new CompositeExecutionProbe();
        $service = new CompositeExecutionAdversarialCommand();
        $service->reportedName = 'different:name';
        $probe->services = [CompositeExecutionAdversarialCommand::class => $service];
        $descriptor = self::adversarialDescriptor(CompositeExecutionAdversarialCommand::class);

        try {
            self::runner($probe)->run($descriptor, self::input(), new CommandOutputBuffer(), 'plain');
            self::fail('A composite service reporting a different name must be rejected.');
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH, $exception->reason());
        }
        self::assertSame(0, $service->runs);
        self::assertSame([], $probe->uowTypes);
        self::assertSame([CompositeExecutionAdversarialCommand::class], $probe->resolved);
    }

    #[DataProvider('invalidExitCodes')]
    public function testOutOfRangeCompositeExitCodesFailWithoutKernelUow(int $invalid): void
    {
        $probe = new CompositeExecutionProbe();
        $service = new CompositeExecutionAdversarialCommand();
        $service->exitCode = $invalid;
        $probe->services = [CompositeExecutionAdversarialCommand::class => $service];

        try {
            self::runner($probe)->run(
                self::adversarialDescriptor(CompositeExecutionAdversarialCommand::class),
                self::input(),
                new CommandOutputBuffer(),
                'json',
            );
            self::fail('An out-of-range composite exit code must fail.');
        } catch (CliCommandFailedException $exception) {
            self::assertSame(CliCommandFailedException::ERROR_CODE, $exception->errorCode());
            self::assertSame(CliCommandFailedException::REASON_COMMAND_FAILED, $exception->reason());
            self::assertSame(
                CliCommandFailedException::ERROR_CODE . ': ' . CliCommandFailedException::REASON_COMMAND_FAILED,
                $exception->getMessage(),
            );
        }
        self::assertSame(1, $service->runs);
        self::assertSame([], $probe->uowTypes);
        self::assertSame(
            ['operation' => WorkflowRunCommand::NAME, 'outcome' => 'failure'],
            $probe->counterCalls[0]['labels']
        );
    }

    /** @return iterable<string, array{int}> */
    public static function invalidExitCodes(): iterable
    {
        yield 'negative' => [-1];
        yield 'above-portable-range' => [256];
    }

    #[DataProvider('telemetryFailurePoints')]
    public function testTelemetryFailuresDoNotChangeCompositeReturnCode(string $failure): void
    {
        $probe = new CompositeExecutionProbe();
        $probe->failure = $failure;
        $service = new CompositeExecutionAdversarialCommand();
        $service->exitCode = 27;
        $probe->services = [CompositeExecutionAdversarialCommand::class => $service];

        self::assertSame(
            27,
            self::runner($probe)->run(
                self::adversarialDescriptor(CompositeExecutionAdversarialCommand::class),
                self::input(),
                new CommandOutputBuffer(),
                'plain',
            ),
        );
        self::assertSame(1, $service->runs);
        self::assertSame([], $probe->uowTypes);
        self::assertSame(1, \count($probe->counterCalls));
        self::assertSame(1, \count($probe->durationCalls));
        self::assertSame(1, \count($probe->logs));
        self::assertSame([
            'operation' => WorkflowRunCommand::NAME,
            'outcome' => 'failure',
            'exit_code' => 27,
        ], $probe->logs[0]['context']);
    }

    #[DataProvider('telemetryFailurePoints')]
    public function testOriginalCompositeThrowableSurvivesTelemetryFailures(string $failure): void
    {
        $probe = new CompositeExecutionProbe();
        $probe->failure = $failure;
        $service = new CompositeExecutionAdversarialCommand();
        $original = new \RuntimeException('PRIVATE-primary-throwable');
        $service->throwable = $original;
        $probe->services = [CompositeExecutionAdversarialCommand::class => $service];

        try {
            self::runner($probe)->run(
                self::adversarialDescriptor(CompositeExecutionAdversarialCommand::class),
                self::input(),
                new CommandOutputBuffer(),
                'plain',
            );
            self::fail('Original command Throwable must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($original, $caught);
        }
        self::assertSame(1, $service->runs);
        self::assertSame([], $probe->uowTypes);
        self::assertSame(1, \count($probe->counterCalls));
        self::assertSame(1, \count($probe->durationCalls));
        self::assertSame(1, \count($probe->logs));
        self::assertSame([
            'operation' => WorkflowRunCommand::NAME,
            'outcome' => 'failure',
            'exit_code' => 1,
        ], $probe->logs[0]['context']);
    }

    /** @return iterable<string, array{string}> */
    public static function telemetryFailurePoints(): iterable
    {
        yield 'tracer' => ['tracer'];
        yield 'span-attributes' => ['span-attributes'];
        yield 'span-end' => ['span-end'];
        yield 'counter' => ['counter'];
        yield 'duration' => ['duration'];
        yield 'logger' => ['logger'];
    }

    private static function input(): ArgvInput
    {
        return new ArgvInput([WorkflowRunCommand::NAME], WorkflowRunCommand::NAME, [], []);
    }

    private static function catalog(): CommandCatalog
    {
        $registry = new TagRegistry();
        foreach ([WorkflowRunCommand::class, CompositeExecutionNormalCommand::class] as $class) {
            $registry->add(ReservedTags::CLI_COMMAND, $class, meta: [
                'name' => $class::NAME,
                'summary' => $class::SUMMARY,
                'group' => $class::GROUP,
                'hidden' => $class::HIDDEN,
                'arguments' => $class::ARGUMENTS,
                'options' => $class::OPTIONS,
            ]);
        }
        return new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
    }

    /**
     * Test-only adversarial descriptor: valid catalog metadata allowlists exactly
     * WorkflowRunCommand, so other composite implementations cannot be dispatched
     * through a production CommandCatalog. This exercises runner fail-closed guards.
     */
    private static function adversarialDescriptor(string $serviceId): CommandDescriptor
    {
        $reflection = new \ReflectionClass(CommandDescriptor::class);
        $descriptor = $reflection->newInstanceWithoutConstructor();
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $constructor->invoke(
            $descriptor,
            $serviceId,
            WorkflowRunCommand::NAME,
            WorkflowRunCommand::SUMMARY,
            WorkflowRunCommand::GROUP,
            false,
            [],
            [],
            'composite',
        );
        return $descriptor;
    }

    private static function runner(CompositeExecutionProbe $probe): CommandRunner
    {
        return new CommandRunner(
            new CompositeExecutionRuntime($probe),
            new CompositeExecutionContext($probe),
            new CompositeExecutionTracer($probe),
            new CompositeExecutionMeter($probe),
            new CompositeExecutionLogger($probe),
            new CompositeExecutionContainer($probe),
            new Stopwatch(),
        );
    }
}

final class CompositeExecutionProbe
{
    public int $spanEnds = 0;
    /** @var array<class-string, object> */
    public array $services = [];
    /** @var list<string> */
    public array $resolved = [];
    /** @var list<string> */
    public array $uowTypes = [];
    /** @var list<array{name: string, attributes: array<string, mixed>}> */
    public array $spanStarts = [];
    /** @var list<array{attributes: array<string, mixed>}> */
    public array $spanFinals = [];
    /** @var list<array{name: string, labels: array<string, mixed>}> */
    public array $counterCalls = [];
    /** @var list<array{name: string, labels: array<string, mixed>}> */
    public array $durationCalls = [];
    /** @var list<array{context: array<string, mixed>}> */
    public array $logs = [];
    public int $normalCalls = 0;
    public int $contextCalls = 0;
    public string $failure = '';
}

final class CompositeExecutionRuntime implements KernelRuntimeInterface
{
    public function __construct(private CompositeExecutionProbe $probe)
    {
    }

    public function runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
    {
        $this->probe->uowTypes[] = $type;
        return $body();
    }

    public function beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
    {
        throw new \LogicException('unexpected-nested-uow');
    }

    public function afterUnitOfWork(
        UnitOfWorkHandle $handle,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array {
        throw new \LogicException('unexpected-low-level-uow');
    }
}

final class CompositeExecutionContext implements ContextAccessorInterface
{
    public function __construct(private CompositeExecutionProbe $probe)
    {
    }

    public function has(string $key): bool
    {
        ++$this->probe->contextCalls;
        return false;
    }

    public function get(string $key): mixed
    {
        ++$this->probe->contextCalls;
        return null;
    }
}

final class CompositeExecutionContainer implements ContainerInterface
{
    public function __construct(private CompositeExecutionProbe $probe)
    {
    }

    public function has(string $id): bool
    {
        throw new \LogicException('service-enumeration-forbidden');
    }

    public function get(string $id): mixed
    {
        $this->probe->resolved[] = $id;
        return $this->probe->services[$id] ?? throw new \LogicException('missing-test-service');
    }
}

final class CompositeExecutionSpan implements SpanInterface
{
    public function __construct(private CompositeExecutionProbe $probe)
    {
    }

    public function name(): string
    {
        return 'cli.command';
    }

    public function setAttribute(string $key, mixed $value): void
    {
        throw new \LogicException('unexpected-single-attribute');
    }

    public function setAttributes(array $attributes): void
    {
        $this->probe->spanFinals[] = ['attributes' => $attributes];
        if ($this->probe->failure === 'span-attributes') {
            throw new \RuntimeException('PRIVATE-telemetry-error');
        }
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        throw new \LogicException('unexpected-span-event');
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
        throw new \LogicException('unexpected-record-exception');
    }

    public function end(): void
    {
        ++$this->probe->spanEnds;
        if ($this->probe->failure === 'span-end') {
            throw new \RuntimeException('PRIVATE-telemetry-error');
        }
    }
}

final class CompositeExecutionTracer implements TracerPortInterface
{
    public function __construct(private CompositeExecutionProbe $probe)
    {
    }

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $this->probe->spanStarts[] = ['name' => $name, 'attributes' => $attributes];
        if ($this->probe->failure === 'tracer') {
            throw new \RuntimeException('PRIVATE-telemetry-error');
        }
        return new CompositeExecutionSpan($this->probe);
    }

    public function inSpan(string $name, callable $callback, array $attributes = []): mixed
    {
        throw new \LogicException('unexpected-span-nesting');
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}

final class CompositeExecutionMeter implements MeterPortInterface
{
    public function __construct(private CompositeExecutionProbe $probe)
    {
    }

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->probe->counterCalls[] = ['name' => $name, 'labels' => $labels];
        if ($this->probe->failure === 'counter') {
            throw new \RuntimeException('PRIVATE-telemetry-error');
        }
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->probe->durationCalls[] = ['name' => $name, 'labels' => $labels];
        if ($this->probe->failure === 'duration') {
            throw new \RuntimeException('PRIVATE-telemetry-error');
        }
    }
}

final class CompositeExecutionLogger extends AbstractLogger
{
    public function __construct(private CompositeExecutionProbe $probe)
    {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->probe->logs[] = ['context' => $context];
        if ($this->probe->failure === 'logger') {
            throw new \RuntimeException('PRIVATE-telemetry-error');
        }
    }
}

final class CompositeExecutionNormalCommand implements CommandInterface
{
    public const string NAME = 'probe:normal';
    public const string SUMMARY = 'Normal step in composite execution.';
    public const string GROUP = 'probe';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct(private CompositeExecutionProbe $probe)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        ++$this->probe->normalCalls;
        $output->text('one normal step');
        return 0;
    }
}

final class CompositeExecutionAdversarialCommand implements CompositeCommandInterface
{
    public string $reportedName = WorkflowRunCommand::NAME;
    public int $exitCode = 0;
    public int $runs = 0;
    public ?\Throwable $throwable = null;

    public function name(): string
    {
        return $this->reportedName;
    }

    public function run(InputInterface $input, OutputInterface $output, string $outputFormat): int
    {
        ++$this->runs;
        if ($this->throwable !== null) {
            throw $this->throwable;
        }
        return $this->exitCode;
    }
}

final class CompositeExecutionBothInterfacesCommand implements CommandInterface, CompositeCommandInterface
{
    public function name(): string
    {
        return WorkflowRunCommand::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output, string $outputFormat = 'plain'): int
    {
        return 0;
    }
}
