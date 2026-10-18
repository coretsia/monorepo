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
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class CommandRunnerResolvesOnlySelectedServiceTest extends TestCase
{
    protected function setUp(): void
    {
        RunnerDispatchProbe::$insideUow = false;
        RunnerSelectedCommand::$constructors = 0;
        RunnerSelectedCommand::$executions = 0;
        RunnerSelectedCommand::$reportedName = RunnerSelectedCommand::NAME;
        RunnerSelectedCommand::$receivedInput = null;
        RunnerSelectedCommand::$receivedOutput = null;
        RunnerUnrelatedCommand::$constructors = 0;
    }

    public function testOnlySelectedServiceIsConstructedInsideUowAndReceivesOriginalPorts(): void
    {
        $catalog = self::catalog(includeUnrelated: true);
        self::assertSame(0, RunnerSelectedCommand::$constructors);
        self::assertSame(0, RunnerUnrelatedCommand::$constructors);
        $input = new ArgvInput(['owner:execute'], RunnerSelectedCommand::NAME, [], []);
        $output = new CommandOutputBuffer();
        $container = new RunnerContainerProbe(
            static function (string $id): mixed {
                if ($id !== RunnerSelectedCommand::class) {
                    throw new \LogicException('unrelated-service-must-not-be-resolved');
                }
                return new RunnerSelectedCommand();
            },
        );
        $runtime = new RunnerRuntimeProbe();

        $code = self::runner($runtime, $container)->run(
            $catalog->require(RunnerSelectedCommand::NAME),
            $input,
            $output,
            'plain',
        );

        self::assertSame(0, $code);
        self::assertSame([RunnerSelectedCommand::class], $container->requested);
        self::assertSame([], $container->hasRequests);
        self::assertSame(1, $runtime->calls);
        self::assertSame(UnitOfWorkType::CLI, $runtime->lastType);
        self::assertSame(
            ['operation' => RunnerSelectedCommand::NAME, 'output_format' => 'plain'],
            $runtime->lastAttributes
        );
        self::assertSame(1, RunnerSelectedCommand::$constructors);
        self::assertSame(1, RunnerSelectedCommand::$executions);
        self::assertSame(0, RunnerUnrelatedCommand::$constructors);
        self::assertSame($input, RunnerSelectedCommand::$receivedInput);
        self::assertSame($output, RunnerSelectedCommand::$receivedOutput);
        self::assertSame([['text' => 'selected', 'type' => 'text']], $output->finalize()->records());
        self::assertFalse(RunnerDispatchProbe::$insideUow);
    }

    public function testResolvedNonCommandServiceIsRejectedWithoutExecution(): void
    {
        self::assertRejected(static fn (): object => new \stdClass());
    }

    public function testDifferentCommandClassWithMatchingNameIsRejected(): void
    {
        self::assertRejected(static fn (): object => new RunnerOtherCommand());
    }

    public function testSubclassDecoratorIsRejectedEvenWhenItsNameMatches(): void
    {
        self::assertRejected(static fn (): object => new RunnerSelectedDecorator());
    }

    public function testExactCommandClassWithIncorrectReportedNameIsRejected(): void
    {
        RunnerSelectedCommand::$reportedName = 'owner:different';
        self::assertRejected(static fn (): object => new RunnerSelectedCommand());
    }

    /** @param \Closure():object $factory */
    private static function assertRejected(\Closure $factory): void
    {
        $descriptor = self::catalog()->require(RunnerSelectedCommand::NAME);
        $container = new RunnerContainerProbe(static fn (string $id): mixed => $factory());
        $runtime = new RunnerRuntimeProbe();
        $input = new ArgvInput(['owner:execute'], RunnerSelectedCommand::NAME, [], []);
        $output = new CommandOutputBuffer();

        try {
            self::runner($runtime, $container)->run($descriptor, $input, $output, 'json');
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH, $exception->reason());
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
            self::assertSame([RunnerSelectedCommand::class], $container->requested);
            self::assertSame([], $container->hasRequests);
            self::assertSame(1, $runtime->calls);
            self::assertSame(0, RunnerSelectedCommand::$executions);
            self::assertFalse(RunnerDispatchProbe::$insideUow);
            return;
        }

        self::fail('Wrong command identity must fail before command execution.');
    }

    private static function catalog(bool $includeUnrelated = false): CommandCatalog
    {
        $registry = new TagRegistry();
        $registry->add(
            ReservedTags::CLI_COMMAND,
            RunnerSelectedCommand::class,
            meta: self::metadata(RunnerSelectedCommand::class),
        );
        if ($includeUnrelated) {
            $registry->add(
                ReservedTags::CLI_COMMAND,
                RunnerUnrelatedCommand::class,
                meta: self::metadata(RunnerUnrelatedCommand::class),
            );
        }

        return new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
    }

    /** @param class-string $class @return array<string, mixed> */
    private static function metadata(string $class): array
    {
        return [
            'name' => $class::NAME,
            'summary' => $class::SUMMARY,
            'group' => $class::GROUP,
            'hidden' => $class::HIDDEN,
            'arguments' => $class::ARGUMENTS,
            'options' => $class::OPTIONS,
        ];
    }

    private static function runner(RunnerRuntimeProbe $runtime, RunnerContainerProbe $container): CommandRunner
    {
        return new CommandRunner(
            runtime: $runtime,
            context: new class() implements ContextAccessorInterface {
                public function has(string $key): bool
                {
                    return false;
                }

                public function get(string $key): mixed
                {
                    throw new \LogicException('unavailable-context-key');
                }
            },
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            logger: new NullLogger(),
            container: $container,
            stopwatch: new Stopwatch(),
        );
    }
}

final class RunnerDispatchProbe
{
    public static bool $insideUow = false;
}

final class RunnerRuntimeProbe implements KernelRuntimeInterface
{
    public int $calls = 0;
    public ?string $lastType = null;
    /** @var array<string, mixed> */
    public array $lastAttributes = [];

    public function runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
    {
        ++$this->calls;
        $this->lastType = $type;
        $this->lastAttributes = $attributes;
        RunnerDispatchProbe::$insideUow = true;
        try {
            return $body();
        } finally {
            RunnerDispatchProbe::$insideUow = false;
        }
    }

    public function beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
    {
        throw new \LogicException('runner-must-use-high-level-uow');
    }

    public function afterUnitOfWork(
        UnitOfWorkHandle $handle,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array {
        throw new \LogicException('runner-must-use-high-level-uow');
    }
}

final class RunnerContainerProbe implements ContainerInterface
{
    /** @var list<string> */
    public array $requested = [];
    /** @var list<string> */
    public array $hasRequests = [];

    /** @param \Closure(string):mixed $factory */
    public function __construct(private \Closure $factory)
    {
    }

    public function get(string $id): mixed
    {
        $this->requested[] = $id;
        return ($this->factory)($id);
    }

    public function has(string $id): bool
    {
        $this->hasRequests[] = $id;
        return false;
    }
}

class RunnerSelectedCommand implements CommandInterface
{
    public const string NAME = 'owner:execute';
    public const string SUMMARY = 'Selected lazy command.';
    public const string GROUP = 'owner';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public static int $constructors = 0;
    public static int $executions = 0;
    public static string $reportedName = self::NAME;
    public static ?InputInterface $receivedInput = null;
    public static ?OutputInterface $receivedOutput = null;

    public function __construct()
    {
        if (!RunnerDispatchProbe::$insideUow) {
            throw new \LogicException('command-constructed-outside-uow');
        }
        ++self::$constructors;
    }

    public function name(): string
    {
        return self::$reportedName;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        ++self::$executions;
        self::$receivedInput = $input;
        self::$receivedOutput = $output;
        $output->text('selected');
        return 0;
    }
}

final class RunnerSelectedDecorator extends RunnerSelectedCommand
{
}

final class RunnerOtherCommand implements CommandInterface
{
    public function name(): string
    {
        return RunnerSelectedCommand::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        throw new \LogicException('wrong-command-must-not-execute');
    }
}

final class RunnerUnrelatedCommand implements CommandInterface
{
    public const string NAME = 'owner:unrelated';
    public const string SUMMARY = 'Unrelated command.';
    public const string GROUP = 'owner';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public static int $constructors = 0;

    public function __construct()
    {
        ++self::$constructors;
        throw new \LogicException('unrelated-construction-forbidden');
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}
