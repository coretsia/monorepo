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

final class WorkflowRunnerDiscardsThrowingChildBufferTest extends TestCase
{
    protected function setUp(): void
    {
        ThrowingChildProbeCommand::$resolutions = [];
        ThrowingChildProbeCommand::$executions = [];
        ThrowingChildProbeCommand::$buffers = [];
        ThrowingChildProbeCommand::$original = null;
    }

    public function testOriginalThrowableDiscardsOnlyCurrentChildWithoutPartialWorkflowOutput(): void
    {
        $original = new \RuntimeException('PRIVATE-original-workflow-step-error');
        ThrowingChildProbeCommand::$original = $original;
        $workflow = new WorkflowDefinition('verify', 'Discard throwing output.', [
            new WorkflowStep(ThrowingChildProbeCommand::NAME, ['completed'], []),
            new WorkflowStep(ThrowingChildProbeCommand::NAME, ['throwing'], []),
            new WorkflowStep(ThrowingChildProbeCommand::NAME, ['unexecuted'], []),
        ]);
        $completedResult = null;
        $captured = '';
        \ob_start();
        try {
            $completedResult = self::runner()->run($workflow, 'table');
            self::fail('The original Throwable must escape WorkflowRunner.');
        } catch (\RuntimeException $caught) {
            self::assertSame($original, $caught);
        } finally {
            $captured = (string) \ob_get_clean();
        }

        self::assertNull($completedResult);
        self::assertSame('', $captured);
        self::assertSame(['completed', 'throwing'], ThrowingChildProbeCommand::$executions);
        self::assertSame(
            [ThrowingChildProbeCommand::class, ThrowingChildProbeCommand::class],
            ThrowingChildProbeCommand::$resolutions
        );
        self::assertCount(2, ThrowingChildProbeCommand::$buffers);
        self::assertNotSame(ThrowingChildProbeCommand::$buffers[0], ThrowingChildProbeCommand::$buffers[1]);
        try {
            ThrowingChildProbeCommand::$buffers[0]->finalize();
            self::fail('The preceding successful step must already be finalized.');
        } catch (CliOutputFormatException) {
            // Previously completed child buffers stay finalized.
        }
        self::assertSame(
            [],
            ThrowingChildProbeCommand::$buffers[1]->finalize()->records(),
            'Discard must erase partial child records before rethrow.'
        );
        try {
            ThrowingChildProbeCommand::$buffers[1]->finalize();
            self::fail('The throwing child buffer must finalize only when explicitly inspected here.');
        } catch (CliOutputFormatException) {
            // The buffer was not finalized by WorkflowRunner.
        }
    }

    public function testWorkflowRunnerHasNoErrorRendererWriterOrIndependentTelemetry(): void
    {
        $sourcePath = new \ReflectionClass(WorkflowRunner::class)->getFileName();
        self::assertIsString($sourcePath);
        $source = \file_get_contents($sourcePath);
        self::assertIsString($source);
        self::assertStringNotContainsString('ExceptionRenderer', $source);
        self::assertStringNotContainsString('OutputFormatter', $source);
        self::assertStringNotContainsString('ConsoleOutputWriter', $source);
        self::assertStringNotContainsString('TracerPortInterface', $source);
        self::assertStringNotContainsString('MeterPortInterface', $source);
        self::assertStringNotContainsString('->error(', $source);
        self::assertStringContainsString('$output->discard();', $source);
        self::assertStringContainsString('throw $exception;', $source);
    }

    private static function runner(): WorkflowRunner
    {
        $class = ThrowingChildProbeCommand::class;
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
                ThrowingChildProbeCommand::$resolutions[] = $id;
                if ($id !== ThrowingChildProbeCommand::class) {
                    throw new \LogicException('An unselected command was resolved.');
                }
                return new ThrowingChildProbeCommand();
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

final class ThrowingChildProbeCommand implements CommandInterface
{
    public const string NAME = 'probe:throw';
    public const string SUMMARY = 'Throw after writing partial child records.';
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
    public static ?\Throwable $original = null;

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $marker = $input->arguments()[0];
        self::$executions[] = $marker;
        if (!$output instanceof CommandOutputBuffer) {
            throw new \LogicException('Expected invocation-local child buffer.');
        }
        self::$buffers[] = $output;
        $output->text('partial-' . $marker);
        $output->json(['marker' => $marker]);
        if ($marker === 'throwing') {
            throw self::$original ?? new \LogicException('Missing original Throwable.');
        }
        return 0;
    }
}
