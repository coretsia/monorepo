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

namespace Coretsia\Platform\Cli\Command;

use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Platform\Cli\Runner\CompositeCommandInterface;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;

/**
 * Platform-owned composite command without an enclosing Kernel CLI UoW.
 *
 * WorkflowRunner dispatches each normal step through CommandRunner; only one
 * completed result is appended to the invocation-local parent output port.
 *
 * @internal
 */
final readonly class WorkflowRunCommand implements CompositeCommandInterface
{
    public const string NAME = 'workflow:run';
    public const string SUMMARY = 'Run one configured CLI workflow.';
    public const string GROUP = 'workflow';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [
        [
            'name' => 'workflow',
            'summary' => 'Workflow name.',
            'required' => true,
            'variadic' => false,
        ],
    ];
    public const array OPTIONS = [];

    public function __construct(
        private WorkflowCatalog $catalog,
        private WorkflowRunner $runner,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output, string $outputFormat): int
    {
        $workflow = $this->catalog->require($input->arguments()[0] ?? '');
        $result = $this->runner->run($workflow, $outputFormat);

        $output->json($result->toArray());

        return $result->exitCode();
    }
}
