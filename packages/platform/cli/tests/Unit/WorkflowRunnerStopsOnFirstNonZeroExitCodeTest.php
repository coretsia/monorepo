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
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\CliOutputFormatException;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use Coretsia\Platform\Cli\Workflow\WorkflowDefinition;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use Coretsia\Platform\Cli\Workflow\WorkflowStep;
use Coretsia\Platform\Cli\Workflow\WorkflowStepInputFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class WorkflowRunnerStopsOnFirstNonZeroExitCodeTest extends TestCase
{
    protected function setUp(): void
    {
        FirstNonZeroProbeCommand::$resolutions = [];
        FirstNonZeroProbeCommand::$executions = [];
        FirstNonZeroProbeCommand::$buffers = [];
    }

    public function testStopsOnFirstNonZeroAndPreservesCompletedChildRecords(): void
    {
        $workflow = new WorkflowDefinition('verify', 'Fail fast at first non-zero step.', [
            new WorkflowStep(FirstNonZeroProbeCommand::NAME, ['first'], []),
            new WorkflowStep(FirstNonZeroProbeCommand::NAME, ['failure'], []),
            new WorkflowStep(FirstNonZeroProbeCommand::NAME, ['unexecuted'], []),
        ]);
        $result = self::runner()->run($workflow, 'json');

        self::assertSame(47, $result->exitCode());
        self::assertSame('failure', $result->outcome());
        self::assertSame(['first', 'failure'], FirstNonZeroProbeCommand::$executions);
        self::assertSame(
            [FirstNonZeroProbeCommand::class, FirstNonZeroProbeCommand::class],
            FirstNonZeroProbeCommand::$resolutions
        );
        self::assertCount(2, FirstNonZeroProbeCommand::$buffers);
        self::assertNotSame(FirstNonZeroProbeCommand::$buffers[0], FirstNonZeroProbeCommand::$buffers[1]);
        foreach (FirstNonZeroProbeCommand::$buffers as $buffer) {
            try {
                $buffer->finalize();
                self::fail('A completed step buffer must already have been finalized.');
            } catch (CliOutputFormatException) {
                // A second finalization is forbidden.
            }
        }
        self::assertSame([
            ['text' => 'completed-first', 'type' => 'text'],
            ['payload' => ['marker' => 'first'], 'type' => 'json'],
        ], $result->steps()[0]->records());
        self::assertSame([
            ['text' => 'completed-failure', 'type' => 'text'],
            ['payload' => ['marker' => 'failure'], 'type' => 'json'],
        ], $result->steps()[1]->records());
        self::assertSame([0, 1], \array_map(static fn ($step): int => $step->index(), $result->steps()));
        self::assertSame([0, 47], \array_map(static fn ($step): int => $step->exitCode(), $result->steps()));
        self::assertSame([
            ['data' => ['marker' => 'first'], 'type' => 'json'],
        ], \array_slice($result->toArray()['steps'][0]['records'], 1));
        self::assertCount(2, $result->toArray()['steps']);
    }

    private static function runner(): WorkflowRunner
    {
        $class = FirstNonZeroProbeCommand::class;
        $registry = new TagRegistry();
        $registry->add(ReservedTags::CLI_COMMAND, $class, meta: [
            'name' => $class::NAME,
            'summary' => $class::SUMMARY,
            'group' => $class::GROUP,
            'hidden' => $class::HIDDEN,
            'arguments' => $class::ARGUMENTS,
            'options' => $class::OPTIONS,
        ]);
        $catalog = new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
        $runtime = new class() implements KernelRuntimeInterface {
            public function runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
            {
                return $body();
            }

            public function beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
            {
                throw new \LogicException('Unexpected Kernel UoW entrypoint.');
            }

            public function afterUnitOfWork(
                UnitOfWorkHandle $handle,
                string $outcome,
                ?\Throwable $error = null,
                array $extensions = [],
            ): array {
                throw new \LogicException('Unexpected Kernel UoW completion.');
            }
        };
        $context = new class() implements ContextAccessorInterface {
            public function has(string $key): bool
            {
                return false;
            }

            public function get(string $key): mixed
            {
                return null;
            }
        };
        $container = new class() implements ContainerInterface {
            public function has(string $id): bool
            {
                throw new \LogicException('Command enumeration is forbidden.');
            }

            public function get(string $id): mixed
            {
                FirstNonZeroProbeCommand::$resolutions[] = $id;
                if ($id !== FirstNonZeroProbeCommand::class) {
                    throw new \LogicException('An unselected command was resolved.');
                }
                return new FirstNonZeroProbeCommand();
            }
        };
        return new WorkflowRunner(
            $catalog,
            new CommandInputValidator(),
            new CommandRunner(
                $runtime,
                $context,
                new NoopTracer(),
                new NoopMeter(),
                new NullLogger(),
                $container,
                new Stopwatch(),
            ),
            new WorkflowStepInputFactory(),
        );
    }
}

final class FirstNonZeroProbeCommand implements CommandInterface
{
    public const string NAME = 'probe:stop';
    public const string SUMMARY = 'Track completed and unexecuted steps.';
    public const string GROUP = 'probe';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [
        ['name' => 'step', 'summary' => 'Step marker.', 'required' => true, 'variadic' => false],
    ];
    public const array OPTIONS = [];

    /** @var list<string> */
    public static array $resolutions = [];
    /** @var list<string> */
    public static array $executions = [];
    /** @var list<CommandOutputBuffer> */
    public static array $buffers = [];

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $marker = $input->arguments()[0];
        self::$executions[] = $marker;
        if (!$output instanceof CommandOutputBuffer) {
            throw new \LogicException('Expected invocation-local child output.');
        }
        self::$buffers[] = $output;
        $output->text('completed-' . $marker);
        $output->json(['marker' => $marker]);
        return $marker === 'failure' ? 47 : 0;
    }
}
