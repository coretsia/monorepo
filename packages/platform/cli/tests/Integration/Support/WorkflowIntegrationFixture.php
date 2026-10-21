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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\CacheVerifyCommand;
use Coretsia\Platform\Cli\Command\ConfigValidateCommand;
use Coretsia\Platform\Cli\Command\WorkflowListCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Kernel\KernelOpsRequestResolver;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use Coretsia\Platform\Cli\Workflow\WorkflowStepInputFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** Real CLI composition for config-defined workflows, reusing existing owner and Ops fixtures. */
final class WorkflowIntegrationFixture
{
    public readonly BoundaryScenarioRuntime $runtime;
    public readonly RecordingKernelOpsPort $ops;
    public readonly WorkflowIntegrationContainer $container;
    public readonly CommandCatalog $commands;
    public readonly WorkflowCatalog $workflows;
    public readonly WorkflowRunner $workflowRunner;
    private readonly CliApplicationFixtureHarness $application;

    /** @param array<string,mixed> $definitions */
    public function __construct(
        array $definitions,
        ?KernelRuntimeInterface $kernelRuntime = null,
        ?ContextAccessorInterface $context = null,
        ?SensitiveDataRedactorInterface $redactor = null,
        bool $withProbe = false,
        ?LoggerInterface $logger = null,
    ) {
        $this->runtime = new BoundaryScenarioRuntime();
        $this->ops = new RecordingKernelOpsPort();
        $registry = new TagRegistry();
        $commandClasses = [
            WorkflowListCommand::class,
            WorkflowRunCommand::class,
            RecordingOwnerCommand::class,
            ConfigValidateCommand::class,
            CacheVerifyCommand::class,
        ];
        if ($withProbe) {
            $commandClasses[] = WorkflowExecutionProbeCommand::class;
        }

        foreach ($commandClasses as $class) {
            $registry->add(ReservedTags::CLI_COMMAND, $class, meta: [
                'name' => $class::NAME,
                'summary' => $class::SUMMARY,
                'group' => $class::GROUP,
                'hidden' => $class::HIDDEN,
                'arguments' => $class::ARGUMENTS,
                'options' => $class::OPTIONS,
            ]);
        }

        $this->commands = new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
        $this->workflows = new WorkflowCatalog(new WorkflowSchema()->normalize($definitions), $this->commands);
        $this->container = new WorkflowIntegrationContainer($this->ops, $withProbe);
        $runtime = $kernelRuntime ?? $this->runtime;
        $context ??= new class() implements ContextAccessorInterface {
            public function has(string $key): bool
            {
                return false;
            }

            public function get(string $key): mixed
            {
                return null;
            }
        };
        $runner = new CommandRunner(
            $runtime,
            $context,
            new NoopTracer(),
            new NoopMeter(),
            $logger ?? new NullLogger(),
            $this->container,
            new Stopwatch(),
        );
        $this->container->workflowList = new WorkflowListCommand($this->workflows);
        $this->workflowRunner = new WorkflowRunner(
            $this->commands,
            new CommandInputValidator(),
            $runner,
            new WorkflowStepInputFactory(),
        );
        $this->container->workflowRun = new WorkflowRunCommand($this->workflows, $this->workflowRunner);

        $redactor ??= new class() implements SensitiveDataRedactorInterface {
            public function redactValue(
                string $value,
                RedactionKind $kind,
                RedactionContext $context,
            ): RedactedValue {
                throw new \LogicException('Direct redaction is forbidden in this test fixture.');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                return $value;
            }
        };
        $this->application = new CliApplicationFixtureHarness(
            $this->commands,
            $runtime,
            $this->container,
            $redactor,
            logger: $logger,
        );
    }

    /** @param list<string> $tokens
     * @return array{exit:int,stdout:string,stderr:string}
     */
    public function run(array $tokens): array
    {
        return $this->application->run($tokens, new TerminalCapabilities(false, false));
    }

    /** @return array<string,mixed> */
    public static function document(array $result): array
    {
        return \json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR);
    }
}

/** Records real lazy service resolution for the selected workflow and step commands. */
final class WorkflowIntegrationContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $resolved = [];
    public ?WorkflowListCommand $workflowList = null;
    public ?WorkflowRunCommand $workflowRun = null;
    public bool $failWorkflowResolution = false;
    public bool $invalidProbeService = false;

    public function __construct(
        private readonly RecordingKernelOpsPort $ops,
        private readonly bool $withProbe = false,
    ) {
    }

    public function get(string $id): mixed
    {
        $this->resolved[] = $id;
        if ($id === WorkflowRunCommand::class && $this->failWorkflowResolution) {
            throw WorkflowDefinitionInvalidException::withReason(
                WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN,
            );
        }

        return match ($id) {
            WorkflowListCommand::class => $this->workflowList,
            WorkflowRunCommand::class => $this->workflowRun,
            RecordingOwnerCommand::class => new RecordingOwnerCommand(),
            WorkflowExecutionProbeCommand::class => $this->withProbe
                ? ($this->invalidProbeService ? new RecordingOwnerCommand() : new WorkflowExecutionProbeCommand())
                : throw new \LogicException('workflow-probe-disabled'),
            ConfigValidateCommand::class => new ConfigValidateCommand($this->ops, new KernelOpsRequestResolver()),
            CacheVerifyCommand::class => new CacheVerifyCommand($this->ops, new KernelOpsRequestResolver()),
            default => throw new \LogicException('workflow-fixture-service-unknown'),
        };
    }

    public function has(string $id): bool
    {
        if ($this->withProbe && $id === WorkflowExecutionProbeCommand::class) {
            return true;
        }

        return \in_array($id, [
            WorkflowListCommand::class,
            WorkflowRunCommand::class,
            RecordingOwnerCommand::class,
            ConfigValidateCommand::class,
            CacheVerifyCommand::class,
        ], true);
    }
}
