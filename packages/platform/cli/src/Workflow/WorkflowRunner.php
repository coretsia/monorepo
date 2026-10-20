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

namespace Coretsia\Platform\Cli\Workflow;

use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Runner\CommandRunner;

/**
 * Stateless sequential workflow-step orchestration over the normal CLI runner.
 *
 * Each executed step receives an independent output buffer and the existing
 * CommandRunner-owned Kernel CLI UoW. No step output is rendered here; only a
 * completed WorkflowResult may reach the parent's final output boundary.
 *
 * @internal
 */
final readonly class WorkflowRunner
{
    public function __construct(
        private CommandCatalog $commands,
        private CommandInputValidator $validator,
        private CommandRunner $runner,
        private WorkflowStepInputFactory $inputs,
    ) {
    }

    public function run(WorkflowDefinition $workflow, string $outputFormat): WorkflowResult
    {
        $steps = [];

        foreach ($workflow->steps() as $index => $step) {
            $descriptor = $this->commands->find($step->command());

            if ($descriptor === null || $descriptor->executionKind() !== 'normal') {
                throw new \LogicException('cli-workflow-step-descriptor-invalid');
            }

            $input = $this->validator->normalizeAndValidate(
                $descriptor,
                $this->inputs->create($step),
            );

            $output = new CommandOutputBuffer();

            try {
                $exitCode = $this->runner->run($descriptor, $input, $output, $outputFormat);
            } catch (\Throwable $exception) {
                $output->discard();

                throw $exception;
            }

            $steps[] = new WorkflowStepResult(
                index: $index,
                command: $descriptor->name(),
                exitCode: $exitCode,
                records: $output->finalize()->records(),
            );

            if ($exitCode !== 0) {
                break;
            }
        }

        $lastStep = $steps[\array_key_last($steps)];

        return new WorkflowResult(
            workflow: $workflow->name(),
            exitCode: $lastStep->exitCode(),
            steps: $steps,
        );
    }
}
