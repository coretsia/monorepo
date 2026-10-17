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
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class CommandRunnerValidatesCommandNameMatchesDescriptorTest extends TestCase
{
    public function testReportedNameMismatchFailsWithCanonicalIdentityReasonBeforeExecution(): void
    {
        $class = RunnerNameMismatchCommand::class;
        $descriptor = new CommandTagSchema()->validate(new TaggedService($class, meta: [
            'name' => $class::NAME,
            'summary' => $class::SUMMARY,
            'group' => $class::GROUP,
            'hidden' => $class::HIDDEN,
            'arguments' => $class::ARGUMENTS,
            'options' => $class::OPTIONS,
        ]));
        $command = new RunnerNameMismatchCommand();
        $container = new RunnerNameMismatchContainer($command, $class);
        $runtime = new RunnerNameMismatchRuntime();
        $context = new RunnerNameMismatchContext();
        $runner = new CommandRunner(
            runtime: $runtime,
            context: $context,
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            logger: new NullLogger(),
            container: $container,
            stopwatch: new Stopwatch(),
        );
        $input = new ArgvInput([$class::NAME], $class::NAME, [], []);
        $output = new CommandOutputBuffer();

        try {
            $runner->run($descriptor, $input, $output, 'plain');
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH, $exception->reason());
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
            self::assertSame(0, $command->executions);
            self::assertSame([$class], $container->requested);
            self::assertSame(1, $runtime->calls);
            self::assertSame(UnitOfWorkType::CLI, $runtime->type);
            self::assertSame([], $output->finalize()->records());
            return;
        }

        self::fail('A command with a different reported name must fail before run().');
    }
}

final class RunnerNameMismatchCommand implements CommandInterface
{
    public const string NAME = 'owner:canonical';
    public const string SUMMARY = 'Command reports a different runtime name.';
    public const string GROUP = 'owner';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public int $executions = 0;

    public function name(): string
    {
        return 'owner:changed';
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        ++$this->executions;
        return 0;
    }
}

final class RunnerNameMismatchRuntime implements KernelRuntimeInterface
{
    public int $calls = 0;
    public ?string $type = null;

    public function runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
    {
        ++$this->calls;
        $this->type = $type;
        return $body();
    }

    public function beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
    {
        throw new \LogicException('unexpected-low-level-begin');
    }

    public function afterUnitOfWork(
        UnitOfWorkHandle $handle,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array {
        throw new \LogicException('unexpected-low-level-completion');
    }
}

final class RunnerNameMismatchContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $requested = [];

    public function __construct(
        private RunnerNameMismatchCommand $command,
        private string $serviceId,
    ) {
    }

    public function get(string $id): mixed
    {
        $this->requested[] = $id;
        if ($id !== $this->serviceId) {
            throw new \LogicException('unexpected-service-request');
        }
        return $this->command;
    }

    public function has(string $id): bool
    {
        throw new \LogicException('runner-must-not-enumerate-services');
    }
}

final class RunnerNameMismatchContext implements ContextAccessorInterface
{
    public function has(string $key): bool
    {
        return false;
    }

    public function get(string $key): mixed
    {
        throw new \LogicException('missing-context');
    }
}
