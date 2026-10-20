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

final class WorkflowRunnerUsesFreshChildBufferPerStepTest extends TestCase
{
    protected function setUp(): void
    {
        FreshChildBufferProbeCommand::$receivedBuffers = [];
        FreshChildBufferProbeCommand::$executions = [];
    }

    public function testEachStepAndInvocationReceivesAFreshFinalizedBufferWithoutRecordLeakage(): void
    {
        $runner = self::runner();
        $workflow = new WorkflowDefinition('verify', 'Verify fresh output buffers.', [
            new WorkflowStep(FreshChildBufferProbeCommand::NAME, ['first'], []),
            new WorkflowStep(FreshChildBufferProbeCommand::NAME, ['second'], []),
        ]);
        $captured = '';
        \ob_start();
        try {
            $first = $runner->run($workflow, 'table');
            $second = $runner->run($workflow, 'table');
        } finally {
            $captured = (string) \ob_get_clean();
        }

        self::assertSame('', $captured, 'WorkflowRunner must never render child stdout.');
        self::assertSame(['first', 'second', 'first', 'second'], FreshChildBufferProbeCommand::$executions);
        self::assertCount(4, FreshChildBufferProbeCommand::$receivedBuffers);
        $identity = [];
        foreach (FreshChildBufferProbeCommand::$receivedBuffers as $buffer) {
            self::assertInstanceOf(CommandOutputBuffer::class, $buffer);
            $identity[] = \spl_object_id($buffer);
            try {
                $buffer->finalize();
                self::fail('A successful child buffer must already be finalized exactly once.');
            } catch (CliOutputFormatException) {
                // The second finalization must be rejected by the existing buffer.
            }
        }
        self::assertCount(4, \array_unique($identity));

        foreach ([$first, $second] as $result) {
            self::assertSame('success', $result->outcome());
            self::assertSame(0, $result->exitCode());
            self::assertSame([0, 1], \array_map(static fn ($step): int => $step->index(), $result->steps()));
            self::assertSame([
                ['text' => 'text-first', 'type' => 'text'],
                ['payload' => ['entry' => 'first'], 'type' => 'json'],
            ], $result->steps()[0]->records());
            self::assertSame([
                ['text' => 'text-second', 'type' => 'text'],
                ['payload' => ['entry' => 'second'], 'type' => 'json'],
            ], $result->steps()[1]->records());
        }
        self::assertSame($first->toArray(), $second->toArray());
    }

    public function testWorkflowServicesDoNotRetainBuffersOrOwnAFormatterOrRedactor(): void
    {
        $reflection = new \ReflectionClass(WorkflowRunner::class);
        $properties = \array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            $reflection->getProperties()
        );
        self::assertSame(['commands', 'validator', 'runner', 'inputs'], $properties);
        self::assertTrue($reflection->isReadOnly());

        $source = $reflection->getFileName();
        self::assertIsString($source);
        $content = \file_get_contents($source);
        self::assertIsString($content);
        self::assertStringNotContainsString('OutputFormatter::', $content);
        self::assertStringNotContainsString('ConsoleOutputWriter::', $content);
        self::assertStringNotContainsString('SensitiveDataRedactorInterface::', $content);
        self::assertStringNotContainsString('->format(', $content);
        self::assertStringNotContainsString('->write(', $content);

        foreach (['STDOUT', 'STDERR', 'php://stdout', 'php://stderr'] as $sink) {
            self::assertStringNotContainsString($sink, $content);
        }
    }

    private static function runner(): WorkflowRunner
    {
        $registry = new TagRegistry();
        $class = FreshChildBufferProbeCommand::class;
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

            public function beginUnitOfWork(
                string $type,
                array $attributes = [],
            ): \Coretsia\Contracts\Runtime\UnitOfWorkHandle {
                throw new \LogicException('unexpected');
            }

            public function afterUnitOfWork(
                \Coretsia\Contracts\Runtime\UnitOfWorkHandle $handle,
                string $outcome,
                ?\Throwable $error = null,
                array $extensions = [],
            ): array {
                throw new \LogicException('unexpected');
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
                throw new \LogicException('enumeration forbidden');
            }

            public function get(string $id): mixed
            {
                if ($id !== FreshChildBufferProbeCommand::class) {
                    throw new \LogicException('unexpected command resolution');
                }
                return new FreshChildBufferProbeCommand();
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

final class FreshChildBufferProbeCommand implements CommandInterface
{
    public const string NAME = 'probe:buffer';
    public const string SUMMARY = 'Probe distinct child buffers.';
    public const string GROUP = 'probe';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [
        ['name' => 'step', 'summary' => 'Step identifier.', 'required' => true, 'variadic' => false],
    ];
    public const array OPTIONS = [];

    /** @var list<OutputInterface> */
    public static array $receivedBuffers = [];
    /** @var list<string> */
    public static array $executions = [];

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $marker = $input->arguments()[0];
        self::$receivedBuffers[] = $output;
        self::$executions[] = $marker;
        $output->text('text-' . $marker);
        $output->json(['entry' => $marker]);
        return 0;
    }
}
